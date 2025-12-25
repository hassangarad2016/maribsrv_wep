<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceReview;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ServiceNotificationService
{
    private const TYPE_REQUEST_CREATED = 'service-request-created';
    private const TYPE_REQUEST_CREATED_PROVIDER = 'service-request-created-provider';
    private const TYPE_REQUEST_UPDATED = 'service-request-update';
    private const TYPE_PAYMENT_CONFIRMED = 'service_payment_confirmed';
    private const TYPE_REVIEW_RECEIVED = 'service-review';
    private const TYPE_REVIEW_UNDER_REVIEW = 'service-review-under-review';

    public function notifyRequestCreated(ServiceRequest $request, User $requester): void
    {
        if ((int) $requester->notification !== 1) {
            return;
        }

        $title = __('service.notifications.request_created.title');
        $body = __('service.notifications.request_created.body', [
            'number' => $this->resolveRequestNumber($request),
            'service' => $this->resolveServiceTitle($request->service),
        ]);

        $this->sendToUser(
            $requester,
            self::TYPE_REQUEST_CREATED,
            $title,
            $body,
            $this->basePayload($request, [
                'status' => $request->status,
                'audience' => 'customer',
            ])
        );
    }

    public function notifyRequestCreatedForProviders(ServiceRequest $request, array $providerIds): void
    {
        $providerIds = $this->normalizeUserIds($providerIds);
        if ($providerIds === []) {
            return;
        }

        $title = __('service.notifications.request_created_provider.title');
        $body = __('service.notifications.request_created_provider.body', [
            'number' => $this->resolveRequestNumber($request),
            'service' => $this->resolveServiceTitle($request->service),
        ]);

        $this->sendToUsers(
            $providerIds,
            self::TYPE_REQUEST_CREATED_PROVIDER,
            $title,
            $body,
            $this->basePayload($request, [
                'status' => $request->status,
                'audience' => 'provider',
            ])
        );
    }

    public function notifyStatusUpdated(ServiceRequest $request): void
    {
        $user = $request->user;
        if (! $user instanceof User || (int) $user->notification !== 1) {
            return;
        }

        $status = strtolower((string) $request->status);
        $titleKey = "service.notifications.status.$status.title";
        $bodyKey = "service.notifications.status.$status.body";
        $fallbackBodyKey = "service.notifications.status.$status.body_fallback";

        $title = __($titleKey);
        $body = __($bodyKey, [
            'number' => $this->resolveRequestNumber($request),
            'service' => $this->resolveServiceTitle($request->service),
            'reason' => $this->limitReason((string) $request->rejected_reason),
        ]);

        if ($body === $bodyKey) {
            $body = __($fallbackBodyKey, [
                'number' => $this->resolveRequestNumber($request),
                'service' => $this->resolveServiceTitle($request->service),
            ]);
        }

        $this->sendToUser(
            $user,
            self::TYPE_REQUEST_UPDATED,
            $title,
            $body,
            $this->basePayload($request, [
                'status' => $request->status,
            ])
        );
    }

    public function notifyPaymentConfirmed(ServiceRequest $request, array $providerIds = []): void
    {
        $customer = $request->user;
        if ($customer instanceof User && (int) $customer->notification === 1) {
            $title = __('service.notifications.payment_confirmed.title');
            $body = __('service.notifications.payment_confirmed.body', [
                'number' => $this->resolveRequestNumber($request),
            ]);

            $this->sendToUser(
                $customer,
                self::TYPE_PAYMENT_CONFIRMED,
                $title,
                $body,
                $this->basePayload($request, [
                    'status' => $request->status,
                    'payment_status' => $request->payment_status,
                    'audience' => 'customer',
                ])
            );
        }

        $providerIds = $this->normalizeUserIds($providerIds);
        if ($providerIds !== []) {
            $title = __('service.notifications.payment_confirmed_provider.title');
            $body = __('service.notifications.payment_confirmed_provider.body', [
                'number' => $this->resolveRequestNumber($request),
                'service' => $this->resolveServiceTitle($request->service),
            ]);

            $this->sendToUsers(
                $providerIds,
                self::TYPE_PAYMENT_CONFIRMED,
                $title,
                $body,
                $this->basePayload($request, [
                    'status' => $request->status,
                    'payment_status' => $request->payment_status,
                    'audience' => 'provider',
                ])
            );
        }
    }

    public function notifyReviewReceived(ServiceReview $review, User $reviewer): void
    {
        $service = $review->service;
        if (! $service instanceof Service) {
            return;
        }

        $ownerId = (int) ($service->owner_id ?? 0);
        if ($ownerId <= 0) {
            return;
        }

        $owner = User::query()->find($ownerId);
        if (! $owner instanceof User || (int) $owner->notification !== 1) {
            return;
        }

        $reviewerName = trim((string) $reviewer->name);
        if ($reviewerName === '') {
            $reviewerName = __('service.notifications.review_received.user_fallback');
        }

        $title = __('service.notifications.review_received.title');
        $body = __('service.notifications.review_received.body', [
            'user' => $reviewerName,
            'service' => $this->resolveServiceTitle($service),
            'rating' => $this->formatRating($review->rating),
        ]);

        $payload = [
            'service_id' => $service->getKey(),
            'service_title' => $service->title,
            'review_id' => $review->getKey(),
            'reviewer_id' => $reviewer->getKey(),
            'rating' => $review->rating,
            'review_status' => $review->status,
            'comment' => $review->review,
            'entity' => 'service-review',
            'entity_id' => $service->getKey() . '-review-' . $review->getKey(),
        ];

        $this->sendToUser(
            $owner,
            self::TYPE_REVIEW_RECEIVED,
            $title,
            $body,
            $payload
        );
    }

    public function notifyReviewUnderReview(ServiceReview $review): void
    {
        $reviewer = $review->user;
        if (! $reviewer instanceof User || (int) $reviewer->notification !== 1) {
            return;
        }

        $service = $review->service;
        if (! $service instanceof Service) {
            return;
        }

        $title = __('service.notifications.review_under_review.title');
        $body = __('service.notifications.review_under_review.body', [
            'service' => $this->resolveServiceTitle($service),
        ]);

        $payload = [
            'service_id' => $service->getKey(),
            'service_title' => $service->title,
            'review_id' => $review->getKey(),
            'review_status' => $review->status,
            'entity' => 'service-review',
            'entity_id' => $service->getKey() . '-review-' . $review->getKey(),
        ];

        $this->sendToUser(
            $reviewer,
            self::TYPE_REVIEW_UNDER_REVIEW,
            $title,
            $body,
            $payload
        );
    }

    private function sendToUser(User $user, string $type, string $title, string $body, array $data): void
    {
        $tokens = $this->resolveTokens($user->getKey());
        if ($tokens === []) {
            return;
        }

        $response = NotificationService::sendFcmNotification($tokens, $title, $body, $type, $data);

        if (is_array($response) && ($response['error'] ?? false)) {
            Log::warning('ServiceNotificationService: notification delivery failed', [
                'user_id' => $user->getKey(),
                'type' => $type,
                'message' => $response['message'] ?? null,
                'details' => $response['details'] ?? null,
                'code' => $response['code'] ?? null,
            ]);
        }
    }

    private function sendToUsers(array $userIds, string $type, string $title, string $body, array $data): void
    {
        $tokens = $this->resolveTokensByUserIds($userIds);
        if ($tokens === []) {
            return;
        }

        $response = NotificationService::sendFcmNotification($tokens, $title, $body, $type, $data);

        if (is_array($response) && ($response['error'] ?? false)) {
            Log::warning('ServiceNotificationService: bulk notification delivery failed', [
                'user_ids' => $userIds,
                'type' => $type,
                'message' => $response['message'] ?? null,
                'details' => $response['details'] ?? null,
                'code' => $response['code'] ?? null,
            ]);
        }
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

    private function resolveTokensByUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return UserFcmToken::query()
            ->whereIn('user_id', $userIds)
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function basePayload(ServiceRequest $request, array $extra = []): array
    {
        $payload = [
            'service_request_id' => $request->getKey(),
            'request_number' => $request->request_number,
            'service_id' => $request->service_id,
            'service_title' => $request->service?->title,
            'user_id' => $request->user_id,
            'entity' => 'service-request',
            'entity_id' => $request->getKey(),
        ];

        return array_merge($payload, $extra);
    }

    private function resolveServiceTitle(?Service $service): string
    {
        if ($service instanceof Service) {
            $title = trim((string) $service->title);
            if ($title !== '') {
                return $title;
            }
        }

        return __('service.notifications.fallback_service_name');
    }

    private function resolveRequestNumber(ServiceRequest $request): string
    {
        $number = trim((string) ($request->request_number ?? ''));
        if ($number !== '') {
            return $number;
        }

        return (string) $request->getKey();
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

    private function normalizeUserIds(array $userIds): array
    {
        return collect($userIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
