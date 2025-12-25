<?php

namespace App\Services;

use App\Models\Item;
use App\Models\SellerRating;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ItemNotificationService
{
    private const TYPE_STATUS_UPDATE = 'item-update';
    private const TYPE_VISIBILITY_UPDATE = 'item-status';
    private const TYPE_REVIEW_RECEIVED = 'item-review';

    public function notifyStatusUpdate(Item $item, string $status, ?string $reason = null): void
    {
        $normalized = strtolower(trim($status));

        if ($normalized === 'review') {
            $this->notifyReviewSubmitted($item);
            return;
        }

        if ($normalized === 'approved') {
            $this->notifyApproved($item);
            return;
        }

        if ($normalized === 'rejected') {
            $this->notifyRejected($item, $reason);
        }
    }

    public function notifyReviewSubmitted(Item $item): void
    {
        $title = __('item.notifications.review.title');
        $body = __('item.notifications.review.body', [
            'item' => $this->resolveItemName($item),
        ]);

        $this->notifyOwner(
            $item,
            self::TYPE_STATUS_UPDATE,
            $title,
            $body,
            [
                'status' => 'review',
            ]
        );
    }

    public function notifyApproved(Item $item): void
    {
        $title = __('item.notifications.approved.title');
        $body = __('item.notifications.approved.body', [
            'item' => $this->resolveItemName($item),
        ]);

        $this->notifyOwner(
            $item,
            self::TYPE_STATUS_UPDATE,
            $title,
            $body,
            [
                'status' => 'approved',
            ]
        );
    }

    public function notifyRejected(Item $item, ?string $reason = null): void
    {
        $reasonText = trim((string) $reason);
        $bodyKey = $reasonText !== ''
            ? 'item.notifications.rejected.body'
            : 'item.notifications.rejected.body_fallback';

        $title = __('item.notifications.rejected.title');
        $body = __($bodyKey, [
            'item' => $this->resolveItemName($item),
            'reason' => $this->limitReason($reasonText),
        ]);

        $this->notifyOwner(
            $item,
            self::TYPE_STATUS_UPDATE,
            $title,
            $body,
            [
                'status' => 'rejected',
                'rejected_reason' => $reasonText,
            ]
        );
    }

    public function notifyVisibilityChange(Item $item, bool $isActive): void
    {
        $status = $isActive ? 'active' : 'blocked';
        $titleKey = $isActive
            ? 'item.notifications.active.title'
            : 'item.notifications.blocked.title';
        $bodyKey = $isActive
            ? 'item.notifications.active.body'
            : 'item.notifications.blocked.body';

        $title = __($titleKey);
        $body = __($bodyKey, ['item' => $this->resolveItemName($item)]);

        $this->notifyOwner(
            $item,
            self::TYPE_VISIBILITY_UPDATE,
            $title,
            $body,
            [
                'status' => $status,
                'entity' => 'item-status',
                'entity_id' => $item->getKey() . '-' . $status,
            ]
        );
    }

    public function notifyReviewReceived(SellerRating $review): void
    {
        $item = $review->item;
        if (! $item instanceof Item) {
            return;
        }

        $seller = $review->seller ?? User::query()->find($review->seller_id);
        if (! $seller instanceof User || (int) $seller->notification !== 1) {
            return;
        }

        $buyerName = trim((string) ($review->buyer?->name ?? ''));
        if ($buyerName === '') {
            $buyerName = __('item.notifications.review_received.buyer_fallback');
        }

        $ratingValue = $this->formatRating($review->ratings);
        $title = __('item.notifications.review_received.title');
        $body = __('item.notifications.review_received.body', [
            'buyer' => $buyerName,
            'item' => $this->resolveItemName($item),
            'rating' => $ratingValue,
        ]);

        $this->sendToUser(
            $seller,
            self::TYPE_REVIEW_RECEIVED,
            $title,
            $body,
            [
                'item_id' => $item->getKey(),
                'review_id' => $review->getKey(),
                'seller_id' => $seller->getKey(),
                'buyer_id' => $review->buyer_id,
                'rating' => $review->ratings,
                'entity' => 'item-review',
                'entity_id' => $item->getKey() . '-review-' . $review->getKey(),
            ]
        );
    }

    private function notifyOwner(Item $item, string $type, string $title, string $body, array $data): void
    {
        $owner = $this->resolveOwner($item);
        if (! $owner instanceof User || (int) $owner->notification !== 1) {
            return;
        }

        $payload = array_merge([
            'item_id' => $item->getKey(),
            'entity' => 'item',
            'entity_id' => $item->getKey() . '-' . ($data['status'] ?? 'update'),
        ], $data);

        $this->sendToUser($owner, $type, $title, $body, $payload);
    }

    private function sendToUser(User $user, string $type, string $title, string $body, array $data): void
    {
        $tokens = $this->resolveTokens($user->getKey());
        if ($tokens === []) {
            return;
        }

        $response = NotificationService::sendFcmNotification(
            $tokens,
            $title,
            $body,
            $type,
            $data
        );

        if (is_array($response) && ($response['error'] ?? false)) {
            Log::warning('ItemNotificationService: notification delivery failed', [
                'user_id' => $user->getKey(),
                'type' => $type,
                'message' => $response['message'] ?? null,
                'details' => $response['details'] ?? null,
                'code' => $response['code'] ?? null,
            ]);
        }
    }

    private function resolveOwner(Item $item): ?User
    {
        if ($item->relationLoaded('user')) {
            return $item->user;
        }

        if ($item->user_id === null) {
            return null;
        }

        return User::query()->find($item->user_id);
    }

    private function resolveItemName(Item $item): string
    {
        $name = trim((string) $item->name);
        return $name !== '' ? $name : __('item.notifications.fallback_item_name');
    }

    private function resolveTokens(int $userId): array
    {
        return UserFcmToken::query()
            ->where('user_id', $userId)
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function formatRating(float|int|string|null $rating): string
    {
        $value = is_numeric($rating) ? (float) $rating : 0.0;
        $formatted = number_format($value, 1, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function limitReason(string $reason): string
    {
        if ($reason === '') {
            return '';
        }

        return Str::limit($reason, 140);
    }
}
