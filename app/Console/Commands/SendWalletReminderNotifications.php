<?php

namespace App\Console\Commands;

use App\Data\Notifications\NotificationIntent;
use App\Enums\NotificationDispatchStatus;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\NotificationDispatchService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SendWalletReminderNotifications extends Command
{
    protected $signature = 'wallet:send-reminders {--dry-run : Report matched users without sending notifications.} {--stage=* : Limit to specific inactivity stages (days).}';

    protected $description = 'Send wallet engagement reminders to inactive users.';

    private const DEFAULT_STAGES = [2, 7, 14];
    private const WINDOW_START_HOUR = 10;
    private const WINDOW_END_HOUR = 21;
    private const CHUNK_SIZE = 250;

    public function handle(): int
    {
        $now = Carbon::now();
        if (! $this->withinWindow($now)) {
            $this->info(sprintf(
                'Wallet reminders skipped: current time %s is outside %02d:00-%02d:00.',
                $now->format('H:i'),
                self::WINDOW_START_HOUR,
                self::WINDOW_END_HOUR
            ));

            return self::SUCCESS;
        }

        $stages = $this->resolveStages();
        if ($stages === []) {
            $this->warn('Wallet reminders skipped: no stages configured.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $summary = [
            'matched' => 0,
            'sent' => 0,
            'deduplicated' => 0,
            'skipped' => 0,
            'dry_run' => 0,
        ];

        $today = $now->copy()->startOfDay();

        $this->buildUserQuery()
            ->chunkById(self::CHUNK_SIZE, function (Collection $users) use ($stages, $today, $dryRun, &$summary): void {
                foreach ($users as $user) {
                    $lastActivity = $this->resolveLastActivity($user);
                    if (! $lastActivity) {
                        continue;
                    }

                    $daysInactive = $lastActivity->copy()->startOfDay()->diffInDays($today);
                    if (! in_array($daysInactive, $stages, true)) {
                        continue;
                    }

                    $summary['matched']++;

                    $type = 'wallet.reminder';
                    $entity = 'wallet_reminder';
                    $stageKey = $this->stageKey($daysInactive, $lastActivity);
                    $fingerprint = $this->buildFingerprint((int) $user->id, $type, $entity, $stageKey);

                    if (NotificationDelivery::query()->where('fingerprint', $fingerprint)->exists()) {
                        $summary['deduplicated']++;
                        continue;
                    }

                    $title = $this->resolveTranslation(
                        sprintf('wallet.reminder.title.stage_%d', $daysInactive),
                        'Wallet reminder'
                    );
                    $body = $this->resolveTranslation(
                        sprintf('wallet.reminder.body.stage_%d', $daysInactive),
                        'Top up your wallet to pay faster in the app.'
                    );

                    if ($dryRun) {
                        $summary['dry_run']++;
                        continue;
                    }

                    $deeplink = config('services.mobile.wallet_deeplink', 'maribsrv://wallet');
                    $intent = new NotificationIntent(
                        userId: (int) $user->id,
                        type: $type,
                        title: $title,
                        body: $body,
                        deeplink: $deeplink,
                        entity: $entity,
                        entityId: $stageKey,
                        data: [
                            'entity' => $entity,
                            'stage' => $daysInactive,
                            'inactivity_days' => $daysInactive,
                            'last_wallet_activity_at' => $lastActivity->toIso8601String(),
                            'deeplink' => $deeplink,
                        ],
                        meta: [
                            'stage' => $daysInactive,
                            'inactivity_days' => $daysInactive,
                            'last_wallet_activity_at' => $lastActivity->toIso8601String(),
                            'stage_key' => $stageKey,
                        ],
                    );

                    $result = app(NotificationDispatchService::class)->dispatch($intent);

                    if ($result->status === NotificationDispatchStatus::Queued) {
                        $summary['sent']++;
                    } else {
                        $summary['skipped']++;
                    }
                }
            }, 'users.id');

        $message = sprintf(
            'Wallet reminders finished: matched %d, sent %d, deduplicated %d, skipped %d',
            $summary['matched'],
            $summary['sent'],
            $summary['deduplicated'],
            $summary['skipped']
        );

        if ($dryRun) {
            $message .= sprintf(', dry-run %d', $summary['dry_run']);
        }

        $this->info($message . '.');

        return self::SUCCESS;
    }

    private function withinWindow(CarbonInterface $now): bool
    {
        $hour = (int) $now->format('G');

        return $hour >= self::WINDOW_START_HOUR && $hour < self::WINDOW_END_HOUR;
    }

    /**
     * @return array<int, int>
     */
    private function resolveStages(): array
    {
        $stages = $this->option('stage');

        if (! is_array($stages) || $stages === []) {
            $stages = self::DEFAULT_STAGES;
        }

        $normalized = [];

        foreach ($stages as $stage) {
            if (! is_numeric($stage)) {
                continue;
            }

            $value = (int) $stage;

            if ($value > 0) {
                $normalized[] = $value;
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    private function buildUserQuery(): Builder
    {
        return User::query()
            ->select('users.id', 'users.created_at')
            ->leftJoin('wallet_accounts', 'wallet_accounts.user_id', '=', 'users.id')
            ->leftJoin('wallet_transactions', 'wallet_transactions.wallet_account_id', '=', 'wallet_accounts.id')
            ->where('users.notification', 1)
            ->whereExists(function (QueryBuilder $query): void {
                $query->select(DB::raw(1))
                    ->from('user_fcm_tokens')
                    ->whereColumn('user_fcm_tokens.user_id', 'users.id');
            })
            ->groupBy('users.id', 'users.created_at')
            ->selectRaw('MAX(wallet_transactions.created_at) as last_wallet_activity_at')
            ->orderBy('users.id');
    }

    private function resolveLastActivity(object $user): ?CarbonInterface
    {
        if (! empty($user->last_wallet_activity_at)) {
            return Carbon::parse($user->last_wallet_activity_at);
        }

        if (! empty($user->created_at)) {
            return $user->created_at instanceof CarbonInterface
                ? $user->created_at->copy()
                : Carbon::parse($user->created_at);
        }

        return null;
    }

    private function stageKey(int $stage, CarbonInterface $lastActivity): string
    {
        return sprintf('%d-%s', $stage, $lastActivity->toDateString());
    }

    private function buildFingerprint(int $userId, string $type, string $entity, string $entityId): string
    {
        return hash('sha256', implode(':', [$userId, $type, $entity, $entityId]));
    }

    private function resolveTranslation(string $key, string $fallback): string
    {
        $value = trans($key);

        if ($value === $key) {
            return $fallback;
        }

        return (string) $value;
    }
}
