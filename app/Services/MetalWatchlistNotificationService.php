<?php

namespace App\Services;

use App\Enums\NotificationFrequency;
use App\Models\MetalRate;
use App\Models\MetalRateChangeLog;
use App\Models\User;
use App\Models\UserPreference;
use App\Notifications\MetalRateCreatedNotification;
use App\Notifications\MetalRateUpdatedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class MetalWatchlistNotificationService
{
    /**
     * @param array<int, array{
     *     governorate_id: int,
     *     governorate_code: string|null,
     *     governorate_name: string|null,
     *     sell_price: string|null,
     *     buy_price: string|null,
     *     is_default: bool
     * }> $quotes
     */
    public function notifyMetalCreated(int $metalId, array $quotes, int $defaultGovernorateId): void
    {
        $metal = MetalRate::query()->find($metalId);

        if (!$metal) {
            return;
        }

        $recipients = $this->resolveBroadcastRecipients();

        if ($recipients->isEmpty()) {
            return;
        }

        $quoteCollection = $this->normalizeQuotes($quotes);

        if ($quoteCollection->isEmpty()) {
            return;
        }

        $defaultQuote = $this->resolveDefaultQuote($quoteCollection, $defaultGovernorateId);

        if (!$defaultQuote) {
            return;
        }

        Notification::send($recipients, new MetalRateCreatedNotification(
            metalId: $metal->getKey(),
            metalName: $metal->display_name,
            governorateId: $defaultQuote['governorate_id'],
            governorateName: $defaultQuote['governorate_name'],
            sellPrice: $defaultQuote['sell_price'],
            buyPrice: $defaultQuote['buy_price']
        ));
    }

    /**
     * @param array<int, array{
     *     governorate_id: int,
     *     governorate_code: string|null,
     *     governorate_name: string|null,
     *     sell_price: string|null,
     *     buy_price: string|null,
     *     is_default: bool
     * }> $quotes
     */
    public function notifyMetalUpdated(int $metalId, array $quotes, int $defaultGovernorateId): void
    {
        $metal = MetalRate::query()->find($metalId);

        if (!$metal) {
            return;
        }

        $recipients = $this->resolveBroadcastRecipients();

        if ($recipients->isEmpty()) {
            return;
        }

        $quoteCollection = $this->normalizeQuotes($quotes);

        if ($quoteCollection->isEmpty()) {
            return;
        }

        $defaultQuote = $this->resolveDefaultQuote($quoteCollection, $defaultGovernorateId);

        if (!$defaultQuote) {
            return;
        }

        $changeSignals = $this->resolveChangeSignals($quoteCollection, $metalId);

        $signal = $changeSignals[$defaultQuote['governorate_id']] ?? null;
        $notification = $signal
            ? new MetalRateUpdatedNotification(
                metalId: $metal->getKey(),
                metalName: $metal->display_name,
                governorateId: $defaultQuote['governorate_id'],
                governorateName: $defaultQuote['governorate_name'],
                sellPrice: $defaultQuote['sell_price'],
                buyPrice: $defaultQuote['buy_price'],
                changePercent: $signal['percent'],
                changeDirection: $signal['direction'],
                notificationType: 'metal_rate_spike',
                titleKey: 'notifications.metal.spike.title',
                bodyKey: 'notifications.metal.spike.body'
            )
            : new MetalRateUpdatedNotification(
                metalId: $metal->getKey(),
                metalName: $metal->display_name,
                governorateId: $defaultQuote['governorate_id'],
                governorateName: $defaultQuote['governorate_name'],
                sellPrice: $defaultQuote['sell_price'],
                buyPrice: $defaultQuote['buy_price']
            );

        Notification::send($recipients, $notification);
    }

    /**
     * @return Collection<int, UserPreference>
     */
    private function resolveWatchPreferences(int $metalId): Collection
    {
        return UserPreference::query()
            ->whereJsonContains('metal_watchlist', $metalId)
            ->with(['user' => static function ($query) {
                $query->with(['fcm_tokens' => static function ($query) {
                    $query->select('id', 'user_id', 'fcm_token');
                }]);
            }])
            ->get()
            ->filter(static function (UserPreference $preference): bool {
                $user = $preference->user;

                if (!$user) {
                    return false;
                }

                return $user->fcm_tokens
                    ->pluck('fcm_token')
                    ->filter()
                    ->isNotEmpty();
            })
            ->unique(static fn (UserPreference $preference) => $preference->user?->getKey())
            ->values();
    }

    /**
     * @param array<int, array{
     *     governorate_id: int,
     *     governorate_code: string|null,
     *     governorate_name: string|null,
     *     sell_price: string|null,
     *     buy_price: string|null,
     *     is_default: bool
     * }> $quotes
     * @return Collection<int, array{
     *     governorate_id: int,
     *     governorate_code: string|null,
     *     governorate_name: string|null,
     *     sell_price: string|null,
     *     buy_price: string|null,
     *     is_default: bool
     * }>
     */
    private function normalizeQuotes(array $quotes): Collection
    {
        return collect($quotes)
            ->map(static function (array $quote): array {
                return [
                    'governorate_id' => (int) ($quote['governorate_id'] ?? 0),
                    'governorate_code' => isset($quote['governorate_code'])
                        ? Str::upper((string) $quote['governorate_code'])
                        : null,
                    'governorate_name' => $quote['governorate_name'] ?? null,
                    'sell_price' => array_key_exists('sell_price', $quote) && $quote['sell_price'] !== null
                        ? (string) $quote['sell_price']
                        : null,
                    'buy_price' => array_key_exists('buy_price', $quote) && $quote['buy_price'] !== null
                        ? (string) $quote['buy_price']
                        : null,
                    'is_default' => (bool) ($quote['is_default'] ?? false),
                ];
            })
            ->filter(static fn (array $quote): bool => $quote['governorate_id'] > 0)
            ->values();
    }

    /**
     * @param Collection<int, array{
     *     governorate_id: int,
     *     governorate_code: string|null,
     *     governorate_name: string|null,
     *     sell_price: string|null,
     *     buy_price: string|null,
     *     is_default: bool
     * }> $quotes
     * @return array{
     *     governorate_id: int,
     *     governorate_code: string|null,
     *     governorate_name: string|null,
     *     sell_price: string|null,
     *     buy_price: string|null,
     *     is_default: bool
     * }|null
     */
    private function resolveDefaultQuote(Collection $quotes, int $defaultGovernorateId): ?array
    {
        if ($defaultGovernorateId > 0) {
            $preferred = $quotes->firstWhere('governorate_id', $defaultGovernorateId);

            if ($preferred) {
                return $preferred;
            }
        }

        $fallback = $quotes->firstWhere('is_default', true);

        if ($fallback) {
            return $fallback;
        }

        return $quotes->first();
    }

    private function frequencyAllows(NotificationFrequency $frequency, int $userId, int $metalId): bool
    {
        return !Cache::has($this->makeThrottleKey($userId, $metalId));
    }

    private function rememberNotification(NotificationFrequency $frequency, int $userId, int $metalId): void
    {
        $ttl = match ($frequency) {
            NotificationFrequency::HOURLY => now()->addHour(),
            NotificationFrequency::DAILY => now()->addDay(),
            default => now()->addHours(6),
        };

        Cache::put($this->makeThrottleKey($userId, $metalId), now(), $ttl);
    }

    private function makeThrottleKey(int $userId, int $metalId): string
    {
        return sprintf('metal-watchlist:%d:%d', $userId, $metalId);
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveBroadcastRecipients(): Collection
    {
        return User::query()
            ->where('notification', 1)
            ->whereHas('fcm_tokens', static function ($query): void {
                $query->whereNotNull('fcm_token')->where('fcm_token', '!=', '');
            })
            ->with(['fcm_tokens' => static function ($query): void {
                $query->select('id', 'user_id', 'fcm_token');
            }])
            ->get();
    }

    /**
     * @param Collection<int, array{
     *     governorate_id: int,
     *     governorate_code: string|null,
     *     governorate_name: string|null,
     *     sell_price: string|null,
     *     buy_price: string|null,
     *     is_default: bool
     * }> $quoteCollection
     * @return array<int, array{percent: float, direction: string}>
     */
    private function resolveChangeSignals(Collection $quoteCollection, int $metalId): array
    {
        $enabled = (bool) config('market-notifications.metal.spike_enabled', false);
        $threshold = (float) config('market-notifications.metal.spike_percent', 0);
        $windowMinutes = (int) config('market-notifications.metal.spike_window_minutes', 0);

        if (!$enabled || $threshold <= 0 || $windowMinutes <= 0) {
            return [];
        }

        $governorateIds = $quoteCollection
            ->pluck('governorate_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($governorateIds)) {
            return [];
        }

        $since = now()->subMinutes($windowMinutes);

        $logs = MetalRateChangeLog::query()
            ->where('metal_rate_id', $metalId)
            ->whereIn('governorate_id', $governorateIds)
            ->where('change_type', 'updated')
            ->where('changed_at', '>=', $since)
            ->orderByDesc('changed_at')
            ->get()
            ->groupBy('governorate_id');

        $signals = [];

        foreach ($logs as $governorateId => $entries) {
            $entry = $entries->first();
            if (!$entry) {
                continue;
            }

            $signal = $this->buildChangeSignal($entry->previous_values, $entry->new_values, $threshold);

            if ($signal !== null) {
                $signals[(int) $governorateId] = $signal;
            }
        }

        return $signals;
    }

    /**
     * @param array<string, mixed>|null $previous
     * @param array<string, mixed>|null $current
     * @return array{percent: float, direction: string}|null
     */
    private function buildChangeSignal(?array $previous, ?array $current, float $threshold): ?array
    {
        if (empty($previous) || empty($current)) {
            return null;
        }

        $sellChange = $this->calculatePercentChange($previous['sell_price'] ?? null, $current['sell_price'] ?? null);
        $buyChange = $this->calculatePercentChange($previous['buy_price'] ?? null, $current['buy_price'] ?? null);

        $candidates = [];

        if ($sellChange !== null) {
            $candidates[] = $sellChange;
        }

        if ($buyChange !== null) {
            $candidates[] = $buyChange;
        }

        if (empty($candidates)) {
            return null;
        }

        $selected = null;

        foreach ($candidates as $change) {
            if ($selected === null || abs($change) > abs($selected)) {
                $selected = $change;
            }
        }

        if ($selected === null || abs($selected) < $threshold) {
            return null;
        }

        return [
            'percent' => round(abs($selected), 2),
            'direction' => $selected > 0 ? 'up' : ($selected < 0 ? 'down' : 'flat'),
        ];
    }

    private function calculatePercentChange(?string $previous, ?string $current): ?float
    {
        if ($previous === null || $current === null) {
            return null;
        }

        $previousValue = (float) $previous;
        $currentValue = (float) $current;

        if ($previousValue <= 0) {
            return null;
        }

        return (($currentValue - $previousValue) / $previousValue) * 100;
    }
}
