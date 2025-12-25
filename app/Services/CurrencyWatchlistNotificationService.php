<?php

namespace App\Services;

use App\Enums\NotificationFrequency;
use App\Models\CurrencyRate;
use App\Models\CurrencyRateChangeLog;
use App\Models\CurrencyRateQuote;
use App\Models\User;
use App\Notifications\CurrencyRateUpdatedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\UserPreference;
use App\Notifications\CurrencyCreatedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;



class CurrencyWatchlistNotificationService
{
    public function notifyCurrencyCreated(int $currencyId, int $defaultGovernorateId): void
    {
        $currency = CurrencyRate::query()->find($currencyId);

        if (!$currency) {
            return;
        }

        $recipients = $this->resolveBroadcastRecipients();

        if ($recipients->isEmpty()) {
            return;
        }

        $defaultQuote = CurrencyRateQuote::query()
            ->with('governorate:id,name')
            ->where('currency_rate_id', $currencyId)
            ->where('governorate_id', $defaultGovernorateId)
            ->first();

        $notification = new CurrencyCreatedNotification(
            currencyId: $currency->getKey(),
            currencyName: $currency->currency_name,
            defaultGovernorateId: $defaultGovernorateId,
            defaultGovernorateName: $defaultQuote?->governorate?->name,
            sellPrice: $defaultQuote?->sell_price,
            buyPrice: $defaultQuote?->buy_price
        );


        Notification::send($recipients, $notification);
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
    public function notifyCurrencyUpdated(int $currencyId, array $quotes): void
    {
        $currency = CurrencyRate::query()->find($currencyId);

        if (!$currency) {
            return;
        }

        $recipients = $this->resolveBroadcastRecipients();

        if ($recipients->isEmpty()) {
            return;
        }

        $quoteCollection = collect($quotes)
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

        if ($quoteCollection->isEmpty()) {
            return;
        }

        $changeSignals = $this->resolveChangeSignals($quoteCollection, $currencyId);

        $defaultQuote = $quoteCollection->firstWhere('is_default', true) ?? $quoteCollection->first();

        if (!$defaultQuote) {
            return;
        }

        $signal = $changeSignals[$defaultQuote['governorate_id']] ?? null;
        $notification = $signal
            ? new CurrencyRateUpdatedNotification(
                currencyId: $currency->getKey(),
                currencyName: $currency->currency_name,
                governorateId: $defaultQuote['governorate_id'],
                governorateName: $defaultQuote['governorate_name'],
                sellPrice: $defaultQuote['sell_price'],
                buyPrice: $defaultQuote['buy_price'],
                changePercent: $signal['percent'],
                changeDirection: $signal['direction'],
                notificationType: 'currency_rate_spike',
                titleKey: 'notifications.currency.spike.title',
                bodyKey: 'notifications.currency.spike.body'
            )
            : new CurrencyRateUpdatedNotification(
                currencyId: $currency->getKey(),
                currencyName: $currency->currency_name,
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
    private function resolveWatchPreferences(int $currencyId): Collection
    {
        return UserPreference::query()
            ->whereJsonContains('currency_watchlist', $currencyId)
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

    private function frequencyAllows(NotificationFrequency $frequency, int $userId, int $currencyId): bool
    {
        return !Cache::has($this->makeThrottleKey($userId, $currencyId));
    }

    private function rememberNotification(NotificationFrequency $frequency, int $userId, int $currencyId): void
    {
        $ttl = match ($frequency) {
            NotificationFrequency::HOURLY => now()->addHour(),
            NotificationFrequency::DAILY => now()->addDay(),
            default => now()->addHours(6),
        };

        Cache::put($this->makeThrottleKey($userId, $currencyId), now(), $ttl);
    }

    private function makeThrottleKey(int $userId, int $currencyId): string
    {
        return sprintf('currency-watchlist:%d:%d', $userId, $currencyId);
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
    private function resolveChangeSignals(Collection $quoteCollection, int $currencyId): array
    {
        $enabled = (bool) config('market-notifications.currency.spike_enabled', false);
        $threshold = (float) config('market-notifications.currency.spike_percent', 0);
        $windowMinutes = (int) config('market-notifications.currency.spike_window_minutes', 0);

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

        $logs = CurrencyRateChangeLog::query()
            ->where('currency_rate_id', $currencyId)
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
