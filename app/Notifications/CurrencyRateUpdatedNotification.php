<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CurrencyRateUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $currencyId,
        public readonly string $currencyName,
        public readonly int $governorateId,
        public readonly ?string $governorateName,
        public readonly ?string $sellPrice,
        public readonly ?string $buyPrice,
        public readonly ?float $changePercent = null,
        public readonly ?string $changeDirection = null,
        public readonly ?string $notificationType = null,
        public readonly ?string $titleKey = null,
        public readonly ?string $bodyKey = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return [FcmChannel::class];
    }

    public function toFcm(object $notifiable): array
    {
        $title = __($this->titleKey ?? 'notifications.currency.updated.title');

        $governorateLabel = $this->governorateName
            ?: __('notifications.currency.updated.governorate_fallback');
        $directionLabel = $this->changeDirection
            ? __('notifications.currency.spike.direction.' . $this->changeDirection)
            : null;
        $body = __($this->bodyKey ?? 'notifications.currency.updated.body', [


            'currency' => $this->currencyName,
            'governorate' => $governorateLabel,
            'direction' => $directionLabel,
            'percent' => $this->formatPercent($this->changePercent),
        ]);

        $priceSegments = [];

        if ($this->sellPrice !== null) {
            $priceSegments[] = __('notifications.currency.price.sell', ['value' => $this->sellPrice]);
        }

        if ($this->buyPrice !== null) {
            $priceSegments[] = __('notifications.currency.price.buy', ['value' => $this->buyPrice]);
        }

        if (!empty($priceSegments)) {
            $body .= ' ' . implode(' ', $priceSegments);
        }

        return [
            'title' => $title,
            'body' => $body,
            'type' => $this->notificationType ?? 'currency_rate_updated',
            'data' => [
                'entity' => 'currency',
                'entity_id' => $this->currencyId,
                'currency_id' => $this->currencyId,
                'governorate_id' => $this->governorateId,
                'sell_price' => $this->sellPrice,
                'buy_price' => $this->buyPrice,
                'change_percent' => $this->changePercent,
                'change_direction' => $this->changeDirection,
            ],
        ];
    }

    private function formatPercent(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
