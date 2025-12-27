<?php

namespace App\Services\Store;

use App\Data\Notifications\NotificationIntent;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\NotificationDispatchService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class StoreNotificationService
{
    private const TYPE_STORE_REVIEW = 'store.review';
    private const TYPE_STORE_STATUS = 'store.status';
    private const TYPE_STORE_ORDER = 'store.order';
    private const DEFAULT_DEEPLINK = 'marib://inbox';

    public function notifyStoreSubmitted(Store $store): void
    {
        $owner = $this->resolveOwner($store);
        if (! $this->canNotify($owner)) {
            return;
        }

        $storeName = $this->resolveStoreName($store);

        $title = __('store.notifications.review.submitted.title');
        $body = __('store.notifications.review.submitted.body', [
            'store' => $storeName,
        ]);

        $this->dispatch(
            $owner,
            self::TYPE_STORE_REVIEW,
            $title,
            $body,
            'store',
            $store->getKey(),
            [
                'store_id' => $store->getKey(),
                'store_name' => $storeName,
                'status' => $store->status,
                'event' => 'review_submitted',
            ]
        );
    }

    public function notifyStoreStatusChanged(Store $store, string $previousStatus, ?string $reason = null): void
    {
        $owner = $this->resolveOwner($store);
        if (! $this->canNotify($owner)) {
            return;
        }

        $storeName = $this->resolveStoreName($store);
        $status = strtolower((string) $store->status);
        $reasonText = $this->limitReason((string) $reason);

        if ($reasonText === '') {
            $reasonText = $this->limitReason((string) $store->rejection_reason);
        }

        $titleKey = "store.notifications.status.$status.title";
        $bodyKey = "store.notifications.status.$status.body";
        $fallbackKey = "store.notifications.status.$status.body_fallback";

        $title = __($titleKey);
        $body = __($bodyKey, [
            'store' => $storeName,
            'reason' => $reasonText,
        ]);

        if ($body === $bodyKey || $reasonText === '') {
            $fallback = __($fallbackKey, [
                'store' => $storeName,
            ]);

            if ($fallback !== $fallbackKey) {
                $body = $fallback;
            }
        }

        $this->dispatch(
            $owner,
            self::TYPE_STORE_STATUS,
            $title,
            $body,
            'store',
            $store->getKey(),
            [
                'store_id' => $store->getKey(),
                'store_name' => $storeName,
                'status' => $store->status,
                'previous_status' => $previousStatus,
                'reason' => $reasonText !== '' ? $reasonText : null,
                'event' => 'status_changed',
            ]
        );
    }

    public function notifyOrderCreated(Order $order): void
    {
        if (! $order->store_id) {
            return;
        }

        $store = $order->store ?? Store::query()->find($order->store_id);
        if (! $store instanceof Store) {
            return;
        }

        $owner = $this->resolveOwner($store);
        if (! $this->canNotify($owner)) {
            return;
        }

        $storeName = $this->resolveStoreName($store);
        $orderNumber = $this->resolveOrderNumber($order);
        $amountValue = $this->resolveOrderAmount($order);
        $currency = $this->resolveOrderCurrency($order);
        $amountFormatted = $this->formatAmount($amountValue);

        $title = __('store.notifications.activity.order_created.title');
        $body = __('store.notifications.activity.order_created.body', [
            'order' => $orderNumber,
            'amount' => $amountFormatted,
            'currency' => $currency,
        ]);

        $this->dispatch(
            $owner,
            self::TYPE_STORE_ORDER,
            $title,
            $body,
            'store-order',
            $order->getKey(),
            [
                'store_id' => $store->getKey(),
                'store_name' => $storeName,
                'order_id' => $order->getKey(),
                'order_number' => $orderNumber,
                'amount' => $amountValue,
                'amount_formatted' => $amountFormatted,
                'currency' => $currency,
                'order_status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'event' => 'order_created',
            ]
        );
    }

    private function dispatch(
        User $user,
        string $type,
        string $title,
        string $body,
        string $entity,
        int|string $entityId,
        array $data
    ): void {
        try {
            app(NotificationDispatchService::class)->dispatch(
                new NotificationIntent(
                    userId: $user->getKey(),
                    type: $type,
                    title: $title,
                    body: $body,
                    deeplink: self::DEFAULT_DEEPLINK,
                    entity: $entity,
                    entityId: $entityId,
                    data: $data,
                    meta: [
                        'source' => 'store-notifications',
                    ],
                )
            );
        } catch (Throwable $exception) {
            Log::error('StoreNotificationService: failed to dispatch notification', [
                'type' => $type,
                'user_id' => $user->getKey(),
                'entity' => $entity,
                'entity_id' => $entityId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveOwner(Store $store): ?User
    {
        $owner = $store->owner;
        if ($owner instanceof User) {
            return $owner;
        }

        return User::query()->find($store->user_id);
    }

    private function canNotify(?User $user): bool
    {
        return $user instanceof User && (int) $user->notification === 1;
    }

    private function resolveStoreName(Store $store): string
    {
        $name = trim((string) $store->name);
        if ($name !== '') {
            return $name;
        }

        return __('store.notifications.fallback_store_name');
    }

    private function resolveOrderNumber(Order $order): string
    {
        $orderNumber = trim((string) $order->order_number);
        if ($orderNumber !== '') {
            return $orderNumber;
        }

        return '#' . $order->getKey();
    }

    private function resolveOrderAmount(Order $order): float
    {
        $amount = $order->final_amount ?? $order->total_amount ?? 0.0;

        return round((float) $amount, 2);
    }

    private function resolveOrderCurrency(Order $order): string
    {
        $snapshot = is_array($order->cart_snapshot) ? $order->cart_snapshot : [];
        $items = $snapshot['items'] ?? [];

        if (is_array($items)) {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $currency = trim((string) ($item['currency'] ?? ''));
                if ($currency !== '') {
                    return strtoupper($currency);
                }
            }
        }

        $pricing = is_array($order->pricing_snapshot) ? $order->pricing_snapshot : [];
        $pricingCurrency = trim((string) ($pricing['currency'] ?? ''));
        if ($pricingCurrency !== '') {
            return strtoupper($pricingCurrency);
        }

        return strtoupper((string) config('app.currency', 'SAR'));
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function limitReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            return '';
        }

        return Str::limit($reason, 200);
    }
}
