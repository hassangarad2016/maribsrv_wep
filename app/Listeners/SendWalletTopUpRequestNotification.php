<?php

namespace App\Listeners;

use App\Events\ManualPaymentRequestCreated;
use App\Models\UserFcmToken;
use App\Services\NotificationService;
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
        $amountText = number_format($amountValue, 2);
        $currency = strtoupper((string) ($manualPaymentRequest->currency ?? config('app.currency', '')));
        $displayAmount = $currency !== '' ? $amountText . ' ' . $currency : $amountText;

        $title = 'تم استلام طلب شحن المحفظة';
        $body = 'تم استلام طلب شحن المحفظة بمبلغ ' . $displayAmount . '، سيتم مراجعة الطلب وسيصلك إشعار بأي تحديث';

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
}
