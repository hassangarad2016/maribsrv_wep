<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MetalRateUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $metalId,
        public readonly string $metalName,
        public readonly int $governorateId,
        public readonly ?string $governorateName,
        public readonly ?string $sellPrice,
        public readonly ?string $buyPrice
    ) {
    }

    public function via(object $notifiable): array
    {
        return [FcmChannel::class];
    }

    public function toFcm(object $notifiable): array
    {
        $title = __('notifications.metal.updated.title');
        $governorateLabel = $this->governorateName
            ?: __('notifications.metal.updated.governorate_fallback');

        $body = __('notifications.metal.updated.body', [
            'metal' => $this->metalName,
            'governorate' => $governorateLabel,
        ]);

        $priceSegments = [];

        if ($this->sellPrice !== null) {
            $priceSegments[] = __('notifications.metal.price.sell', ['value' => $this->sellPrice]);
        }

        if ($this->buyPrice !== null) {
            $priceSegments[] = __('notifications.metal.price.buy', ['value' => $this->buyPrice]);
        }

        if (!empty($priceSegments)) {
            $body .= ' ' . implode(' ', $priceSegments);
        }

        return [
            'title' => $title,
            'body' => $body,
            'type' => 'metal_rate_updated',
            'data' => [
                'entity' => 'metal',
                'entity_id' => $this->metalId,
                'metal_id' => $this->metalId,
                'governorate_id' => $this->governorateId,
                'sell_price' => $this->sellPrice,
                'buy_price' => $this->buyPrice,
            ],
        ];
    }
}
