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

class SendSmartWalletNotifications extends Command
{
    protected $signature = 'wallet:send-smart {--dry-run : Report matched users without sending notifications.}';

    protected $description = 'Send smart wallet notifications based on balance and inactivity.';

    private const WINDOW_START_HOUR = 10;
    private const WINDOW_END_HOUR = 21;
    private const CHUNK_SIZE = 250;

    public function handle(): int
    {
        $now = Carbon::now();

        if (! $this->withinWindow($now)) {
            $this->info(sprintf(
                'Smart wallet notifications skipped: current time %s is outside %02d:00-%02d:00.',
                $now->format('H:i'),
                self::WINDOW_START_HOUR,
                self::WINDOW_END_HOUR
            ));

            return self::SUCCESS;
        }

        $config = $this->config();
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
            ->chunkById(self::CHUNK_SIZE, function (Collection $users) use ($config, $today, $dryRun, &$summary): void {
                foreach ($users as $row) {
                    $balance = (float) ($row->wallet_balance ?? 0.0);
                    $currency = (string) ($row->wallet_currency ?? config('wallet.currency', 'YER'));

                    if ($balance <= 0) {
                        continue;
                    }

                    $lastActivity = $this->resolveLastActivity($row);
                    if (! $lastActivity) {
                        continue;
                    }

                    $daysInactive = $lastActivity->copy()->startOfDay()->diffInDays($today);
                    $summary['matched']++;

                    $rule = $this->resolveRule($balance, $daysInactive, $config);
                    if ($rule === null) {
                        continue;
                    }

                    if ($this->recentlySent((int) $row->id, $rule['type'], $rule['cooldown_days'])) {
                        $summary['deduplicated']++;
                        continue;
                    }

                    $title = trans($rule['title_key']);
                    $body = trans($rule['body_key'], [
                        'balance' => number_format($balance, 2),
                        'currency' => $currency,
                        'days' => $daysInactive,
                    ]);

                    $deeplink = config('services.mobile.wallet_deeplink', 'maribsrv://wallet');

                    if ($dryRun) {
                        $summary['dry_run']++;
                        continue;
                    }

                    $intent = new NotificationIntent(
                        userId: (int) $row->id,
                        type: $rule['type'],
                        title: $title,
                        body: $body,
                        deeplink: $deeplink,
                        entity: 'wallet_smart',
                        entityId: $rule['type'],
                        data: [
                            'entity' => 'wallet_smart',
                            'rule' => $rule['type'],
                            'balance' => $balance,
                            'currency' => $currency,
                            'days_inactive' => $daysInactive,
                            'last_wallet_activity_at' => $lastActivity->toIso8601String(),
                            'deeplink' => $deeplink,
                        ],
                        meta: [
                            'rule' => $rule['type'],
                            'balance' => $balance,
                            'currency' => $currency,
                            'days_inactive' => $daysInactive,
                            'last_wallet_activity_at' => $lastActivity->toIso8601String(),
                        ],
                    );

                    $result = app(NotificationDispatchService::class)->dispatch($intent);

                    if ($result->status === NotificationDispatchStatus::Queued) {
                        $summary['sent']++;
                    } else {
                        $summary['skipped']++;
                    }
                }
            }, 'id');

        $this->info(sprintf(
            'Smart wallet notifications finished: matched %d, sent %d, deduplicated %d, skipped %d%s.',
            $summary['matched'],
            $summary['sent'],
            $summary['deduplicated'],
            $summary['skipped'],
            $dryRun ? sprintf(', dry-run %d', $summary['dry_run']) : ''
        ));

        return self::SUCCESS;
    }

    private function withinWindow(CarbonInterface $now): bool
    {
        $hour = (int) $now->format('G');

        return $hour >= self::WINDOW_START_HOUR && $hour < self::WINDOW_END_HOUR;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'low_balance_threshold' => (float) config('wallet.notifications.low_balance_threshold', 1000),
            'low_balance_min_inactivity_days' => (int) config('wallet.notifications.low_balance_min_inactivity_days', 2),
            'low_balance_cooldown_days' => (int) config('wallet.notifications.low_balance_cooldown_days', 7),
            'inactive_balance_days' => (int) config('wallet.notifications.inactive_balance_days', 10),
            'inactive_balance_cooldown_days' => (int) config('wallet.notifications.inactive_balance_cooldown_days', 10),
        ];
    }

    private function resolveRule(float $balance, int $daysInactive, array $config): ?array
    {
        if ($balance <= $config['low_balance_threshold']
            && $daysInactive >= $config['low_balance_min_inactivity_days']) {
            return [
                'type' => 'wallet.smart.low_balance',
                'title_key' => 'wallet.smart.low_balance.title',
                'body_key' => 'wallet.smart.low_balance.body',
                'cooldown_days' => $config['low_balance_cooldown_days'],
            ];
        }

        if ($daysInactive >= $config['inactive_balance_days']) {
            return [
                'type' => 'wallet.smart.inactive_balance',
                'title_key' => 'wallet.smart.inactive_balance.title',
                'body_key' => 'wallet.smart.inactive_balance.body',
                'cooldown_days' => $config['inactive_balance_cooldown_days'],
            ];
        }

        return null;
    }

    private function buildUserQuery(): Builder
    {
        return User::query()
            ->select([
                DB::raw('users.id as id'),
                'users.created_at',
                'wallet_accounts.balance as wallet_balance',
                'wallet_accounts.currency as wallet_currency',
            ])
            ->join('wallet_accounts', 'wallet_accounts.user_id', '=', 'users.id')
            ->leftJoin('wallet_transactions', 'wallet_transactions.wallet_account_id', '=', 'wallet_accounts.id')
            ->where('users.notification', 1)
            ->whereExists(function (QueryBuilder $query): void {
                $query->select(DB::raw(1))
                    ->from('user_fcm_tokens')
                    ->whereColumn('user_fcm_tokens.user_id', 'users.id');
            })
            ->groupBy('users.id', 'users.created_at', 'wallet_accounts.balance', 'wallet_accounts.currency')
            ->selectRaw('MAX(wallet_transactions.created_at) as last_wallet_activity_at')
            ->orderBy('users.id');
    }

    private function resolveLastActivity(object $row): ?CarbonInterface
    {
        if (! empty($row->last_wallet_activity_at)) {
            return Carbon::parse($row->last_wallet_activity_at);
        }

        if (! empty($row->created_at)) {
            return $row->created_at instanceof CarbonInterface
                ? $row->created_at->copy()
                : Carbon::parse($row->created_at);
        }

        return null;
    }

    private function recentlySent(int $userId, string $type, int $cooldownDays): bool
    {
        if ($cooldownDays <= 0) {
            return false;
        }

        $cutoff = Carbon::now()->subDays($cooldownDays);

        return NotificationDelivery::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('created_at', '>=', $cutoff)
            ->exists();
    }
}
