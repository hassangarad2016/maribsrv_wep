<?php

namespace App\Listeners;

use App\Events\ManualPaymentRequestCreated;
use App\Models\UserFcmToken;
use App\Services\NotificationService;
use App\Support\ManualPayments\TransferDetailsResolver;
use Illuminate\Support\Facades\Log;

class SendWalletTopUpRequestNotification
{
    public function handle(ManualPaymentRequestCreated $event): void
    {
        $manualPaymentRequest = $event->manualPaymentRequest;

        if (! $manualPaymentRequest->isWalletTopUp() || ! $manualPaymentRequest->isOpen()) {
            return;
        }

        if (! $manualPaymentRequest->user_id) {
            return;
        }

        $tokens = UserFcmToken::query()
            ->where('user_id', $manualPaymentRequest->user_id)
            ->pluck('fcm_token')
            ->filter()
            ->values()
            ->all();

        if ($tokens === []) {
            return;
        }

        $amountValue = (float) $manualPaymentRequest->amount;
        $currency = strtoupper((string) ($manualPaymentRequest->currency ?? config('app.currency', '')));
        $details = $this->buildWalletTopUpDetails($manualPaymentRequest);
        $title = __('manual_payment.notification.requested.title');
        $body = __('manual_payment.notification.requested.body', ['details' => $details]);

        $payload = [
            'manual_payment_request_id' => $manualPaymentRequest->getKey(),
            'status' => $manualPaymentRequest->status,
            'reference' => $manualPaymentRequest->reference ?? $manualPaymentRequest->getKey(),
            'event' => 'wallet_top_up_request',
            'category' => 'wallet_top_up',
            'wallet_amount' => $amountValue,
            'wallet_currency' => $currency,
        ];

        try {
            NotificationService::sendFcmNotification($tokens, $title, $body, 'wallet', $payload);
        } catch (\Throwable $exception) {
            Log::error('wallet_top_up_request_notification_failed', [
                'manual_payment_request_id' => $manualPaymentRequest->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function buildWalletTopUpDetails($manualPaymentRequest): string
    {
        $segments = [];

        $amount = $this->formatNotificationAmount($manualPaymentRequest);
        if ($amount !== null) {
            $segments[] = __('manual_payment.notification.detail.amount', ['amount' => $amount]);
        }

        $requestReference = $this->normalizeNotificationValue(
            $manualPaymentRequest->reference ?? $manualPaymentRequest->getKey()
        );
        if ($requestReference !== null) {
            $segments[] = __('manual_payment.notification.detail.request_reference', [
                'reference' => $requestReference,
            ]);
        }

        $transferDetails = TransferDetailsResolver::forManualPaymentRequest($manualPaymentRequest)->toArray();

        $transferReference = $this->normalizeNotificationValue($transferDetails['transfer_reference'] ?? null);
        if ($transferReference !== null && $transferReference !== $requestReference) {
            $segments[] = __('manual_payment.notification.detail.transfer_reference', [
                'reference' => $transferReference,
            ]);
        }

        $bankName = $this->normalizeNotificationValue(
            $transferDetails['bank_name']
                ?? $manualPaymentRequest->bank_label
                ?? $manualPaymentRequest->bank_name
                ?? $manualPaymentRequest->gateway_label
        );
        if ($bankName !== null) {
            $segments[] = __('manual_payment.notification.detail.bank_name', ['bank' => $bankName]);
        }

        $senderName = $this->normalizeNotificationValue($transferDetails['sender_name'] ?? null);
        if ($senderName !== null) {
            $segments[] = __('manual_payment.notification.detail.sender_name', ['name' => $senderName]);
        }

        $segments[] = __('manual_payment.notification.detail.type.wallet_top_up');

        $segments = array_values(array_filter(
            $segments,
            static fn ($segment) => is_string($segment) && trim($segment) !== ''
        ));

        if ($segments === []) {
            return __('manual_payment.notification.detail.not_available');
        }

        return implode(' | ', $segments);
    }

    private function formatNotificationAmount($manualPaymentRequest): ?string
    {
        $amount = $manualPaymentRequest->amount;
        if ($amount === null || $amount === '') {
            return null;
        }

        $amountValue = is_numeric($amount) ? (float) $amount : null;
        if ($amountValue === null) {
            return null;
        }

        $amountText = number_format($amountValue, 2);
        $currency = strtoupper((string) ($manualPaymentRequest->currency ?? config('app.currency', '')));

        return $currency !== '' ? $amountText . ' ' . $currency : $amountText;
    }

    private function normalizeNotificationValue(mixed $value): ?string
    {
        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        if ($value === null || is_bool($value)) {
            return null;
        }

        if (is_numeric($value) && ! is_string($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
