<?php

namespace App\Services;

use App\Data\Notifications\NotificationIntent;
use App\Enums\NotificationType;
use App\Models\ManualPaymentRequest;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\WalletAudit;
use App\Models\WalletUsageLimit;

use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\DB;
use App\Services\NotificationDispatchService;
use Symfony\Component\HttpKernel\Exception\HttpException;

use InvalidArgumentException;
use RuntimeException;
use Throwable;


class WalletService
{
    public function getPrimaryCurrency(): string
    {
        return strtoupper((string) config('wallet.currency', 'YER'));
    }

    public function credit(User $user, string $idempotencyKey, float $amount, array $options = []): WalletTransaction
    {
        return $this->record($user, 'credit', $idempotencyKey, $amount, $options);
    }

    public function debit(User $user, string $idempotencyKey, float $amount, array $options = []): WalletTransaction
    {
        return $this->record($user, 'debit', $idempotencyKey, $amount, $options);
    }


    public function ensureSufficient(User|int $user, float $amount, string $currency): void
    {
        if ($amount <= 0) {
            return;
        }

        $userModel = $user instanceof User ? $user : User::query()->find($user);

        if (! $userModel instanceof User) {
            throw new InvalidArgumentException('Unable to resolve user for wallet balance verification.');
        }

        $normalizedCurrency = strtoupper($currency);

        $account = $this->findAccount($userModel, $normalizedCurrency);

        if (! $account instanceof WalletAccount) {
            throw new HttpException(402, __('رصيد المحفظة غير كافٍ.'));
        }

        $lockedAccount = WalletAccount::query()
            ->whereKey($account->getKey())
            ->lockForUpdate()
            ->first();

        if (! $lockedAccount instanceof WalletAccount || (float) $lockedAccount->balance < $amount) {
            throw new HttpException(402, __('رصيد المحفظة غير كافٍ.'));
        }
    }

    public function deductAndLog(
        User|int $user,
        float $amount,
        string $currency,
        string $reason,
        ?int $relatedPaymentTransactionId = null,
        ?string $idempotencyKey = null,
        array $meta = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        $userModel = $user instanceof User ? $user : User::query()->find($user);

        if (! $userModel instanceof User) {
            throw new InvalidArgumentException('Unable to resolve user for wallet deduction.');
        }

        $normalizedCurrency = strtoupper($currency);

        $metaPayload = array_replace_recursive([
            'reason' => $reason,
        ], $meta);

        $options = [
            'currency' => $normalizedCurrency,
            'meta' => $metaPayload,
        ];

        if ($relatedPaymentTransactionId !== null) {
            $options['payment_transaction_id'] = $relatedPaymentTransactionId;
        }

        $idempotency = $idempotencyKey ?? (string) Str::uuid();

        return $this->debit($userModel, $idempotency, $amount, $options);
    }

    protected function record(User $user, string $direction, string $idempotencyKey, float $amount, array $options = []): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        if (!in_array($direction, ['credit', 'debit'], true)) {
            throw new InvalidArgumentException('Invalid wallet transaction direction provided.');
        }


        $transactionCurrency = strtoupper((string) ($options['currency'] ?? config('app.currency', 'SAR')));


        $walletTransaction = DB::transaction(function () use ($user, $direction, $idempotencyKey, $amount, $options, $transactionCurrency) {
            $walletAccount = $this->resolveWalletAccount($user, $transactionCurrency, true);

            $existing = WalletTransaction::query()
                ->where('wallet_account_id', $walletAccount->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->exists();

            if ($existing) {
                throw new RuntimeException('A wallet transaction with the provided idempotency key already exists.');
            }


            $usageRecords = $this->prepareUsageLimitRecords($walletAccount, $direction, $amount);


            $currentBalance = (float) $walletAccount->balance;
            $adjustment = $direction === 'credit' ? $amount : -$amount;
            $newBalance = round($currentBalance + $adjustment, 2);

            if ($newBalance < 0) {
                throw new RuntimeException('Insufficient wallet balance for the requested operation.');
            }

            $transactionData = [
                'wallet_account_id' => $walletAccount->getKey(),
                'type' => $direction,
                'amount' => $amount,
                'currency' => $transactionCurrency,
                'balance_after' => $newBalance,
                'idempotency_key' => $idempotencyKey,
            ];

            if (isset($options['manual_payment_request']) && $options['manual_payment_request'] instanceof ManualPaymentRequest) {
                $transactionData['manual_payment_request_id'] = $options['manual_payment_request']->getKey();
            } elseif (!empty($options['manual_payment_request_id'])) {
                $transactionData['manual_payment_request_id'] = $options['manual_payment_request_id'];
            }

            if (isset($options['payment_transaction']) && $options['payment_transaction'] instanceof PaymentTransaction) {
                $transactionData['payment_transaction_id'] = $options['payment_transaction']->getKey();
            } elseif (!empty($options['payment_transaction_id'])) {
                $transactionData['payment_transaction_id'] = $options['payment_transaction_id'];
            }

            if (!empty($options['meta'])) {
                $transactionData['meta'] = $options['meta'];
            }

            try {
                $walletTransaction = WalletTransaction::create($transactionData);
            } catch (QueryException $exception) {
                // In case of a race condition, rethrow as a runtime exception for upstream handling.
                throw new RuntimeException('Failed to create wallet transaction: ' . $exception->getMessage(), 0, $exception);
            }

            $walletAccount->forceFill(['balance' => $newBalance])->save();

            $this->persistUsageLimitRecords($usageRecords, $direction);

            if (!empty($options['audit'])) {
                $this->recordAuditEvent($walletTransaction, $options['audit']);
            }



            return $walletTransaction;
        });


        $this->sendWalletNotification($user, $walletTransaction);

        return $walletTransaction;
    }



    public function hasAccount(User $user, ?string $currency = null): bool
    {
        $query = WalletAccount::query()->where('user_id', $user->getKey());

        if ($currency !== null) {
            $query->where('currency', strtoupper($currency));
        }

        return $query->exists();
    }

    public function findAccount(User $user, string $currency): ?WalletAccount
    {
        $normalizedCurrency = strtoupper($currency);

        $account = WalletAccount::query()
            ->where('user_id', $user->getKey())
            ->where('currency', $normalizedCurrency)
            ->first();

        if ($account instanceof WalletAccount) {
            return $account;
        }

        /** @var WalletAccount|null $existing */
        $existing = WalletAccount::query()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->first();

        if (! $existing instanceof WalletAccount) {
            return null;
        }

        if ($existing->currency !== $normalizedCurrency) {
            $existing->currency = $normalizedCurrency;
            $existing->save();

            WalletTransaction::query()
                ->where('wallet_account_id', $existing->getKey())
                ->update(['currency' => $normalizedCurrency]);
        }

        return $existing;
    }

    protected function resolveWalletAccount(User $user, string $currency, bool $lockForUpdate = false): WalletAccount
    {
        $currency = strtoupper($currency);

        $query = WalletAccount::query()
            ->where('user_id', $user->getKey())
            ->where('currency', $currency);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $walletAccount = $query->first();

        if (! $walletAccount) {
            $walletAccount = WalletAccount::create([
                'user_id' => $user->getKey(),
                'currency' => $currency,
                'balance' => 0,
            ]);

            if ($lockForUpdate) {
                $walletAccount = WalletAccount::query()
                    ->whereKey($walletAccount->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        return $walletAccount;
    }

    public function buildWalletNotificationPayload(WalletTransaction $transaction): array
    {
        $currency = strtoupper((string) ($transaction->currency ?? config('app.currency', 'SAR')));
        $amount = (float) $transaction->amount;
        $balance = (float) $transaction->balance_after;
        $balanceBefore = $transaction->type === 'credit'
            ? round($balance - $amount, 2)
            : round($balance + $amount, 2);
        $rawMeta = $transaction->meta;
        if (!is_array($rawMeta)) {
            $rawMeta = [];
        }

        $reason = (string) data_get($rawMeta, 'reason', '');
        $context = (string) data_get($rawMeta, 'context', '');
        $transferDirection = (string) data_get($rawMeta, 'direction', '');
        $counterpartyName = trim((string) data_get($rawMeta, 'counterparty.name', ''));

        $reference = null;
        $referenceCandidates = [
            data_get($rawMeta, 'operation_reference'),
            data_get($rawMeta, 'transfer_reference'),
            data_get($rawMeta, 'reference'),
            data_get($rawMeta, 'wallet_reference'),
            data_get($rawMeta, 'withdrawal_request_reference'),
            data_get($rawMeta, 'manual.transfer_reference'),
            data_get($rawMeta, 'manual.metadata.transfer_reference'),
            data_get($rawMeta, 'metadata.transfer_reference'),
            data_get($rawMeta, 'metadata.reference'),
            data_get($rawMeta, 'transfer.transfer_reference'),
            data_get($rawMeta, 'transfer.reference'),
            data_get($rawMeta, 'transfer_details.transfer_reference'),
            data_get($rawMeta, 'transfer_details.reference'),
        ];

        foreach ($referenceCandidates as $candidate) {
            if (is_scalar($candidate)) {
                $candidate = trim((string) $candidate);
                if ($candidate !== '') {
                    $reference = $candidate;
                    break;
                }
            }
        }

        $currencyLabel = $this->formatCurrencyLabel($currency);
        $amountFormatted = number_format($amount, 2) . ' ' . $currencyLabel;
        $balanceFormatted = number_format($balance, 2) . ' ' . $currencyLabel;
        $referenceText = $this->formatReferenceText($reference);
        $balanceText = "رصيدك بعد العملية: {$balanceFormatted}.";
        $orderId = data_get($rawMeta, 'order_id');
        $orderText = $orderId ? "رقم الطلب: {$orderId}." : '';

        $title = 'تحديث في المحفظة';
        $mainText = "تم تحديث رصيد محفظتك بمبلغ {$amountFormatted}.";

        if ($context === 'wallet_withdrawal_request') {
            $title = 'طلب سحب من المحفظة';
            $mainText = "تم خصم {$amountFormatted} لتجهيز طلب السحب.";
        } elseif ($context === 'wallet_withdrawal_request_reversal') {
            $title = 'إلغاء طلب سحب';
            $mainText = "تم إعادة {$amountFormatted} إلى محفظتك بعد رفض طلب السحب.";
        } elseif ($reason === 'admin_manual_credit') {
            $title = 'إيداع من الإدارة';
            $mainText = "تم إيداع {$amountFormatted} في محفظتك من الإدارة.";
        } elseif ($reason === ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP || $reason === 'wallet_top_up' || $transaction->manual_payment_request_id) {
            $title = 'شحن المحفظة';
            $mainText = "تم شحن محفظتك بمبلغ {$amountFormatted}.";
        } elseif ($reason === 'wallet_transfer' || $context === 'wallet_transfer') {
            if ($transferDirection === 'incoming' || $transaction->type === 'credit') {
                $title = 'تحويل وارد للمحفظة';
                $mainText = $counterpartyName !== ''
                    ? "تم استلام تحويل بقيمة {$amountFormatted} من {$counterpartyName}."
                    : "تم استلام تحويل بقيمة {$amountFormatted}.";
            } else {
                $title = 'تحويل صادر من المحفظة';
                $mainText = $counterpartyName !== ''
                    ? "تم تحويل {$amountFormatted} إلى {$counterpartyName}."
                    : "تم تحويل {$amountFormatted}.";
            }
        } elseif (in_array($reason, ['refund', 'wallet_refund'], true)) {
            $title = 'استرجاع للمحفظة';
            $mainText = "تم إرجاع {$amountFormatted} إلى محفظتك.";
        } elseif ($transaction->type === 'debit') {
            $title = 'خصم من المحفظة';
            $mainText = "تم خصم {$amountFormatted} من محفظتك.";
        } else {
            $title = 'إضافة إلى المحفظة';
            $mainText = "تم إضافة {$amountFormatted} إلى محفظتك.";
        }

        $body = trim(implode(' ', array_filter([
            $mainText,
            $orderText,
            $referenceText,
            $balanceText,
        ])));

        $meta = $this->sanitizeMetadataForNotification($rawMeta);

        $data = [

            'title' => $title,
            'body' => $body,
            'type' => 'wallet',
            'data' => [
                'transaction_id' => $transaction->getKey(),
                'transaction_type' => $transaction->type,
                'amount' => $amount,
                'balance' => $balance,
                'balance_before' => $balanceBefore,
                'currency' => $currency,
                'reason' => $reason,
                'context' => $context,
                'direction' => $transferDirection,
                'counterparty_name' => $counterpartyName !== '' ? $counterpartyName : null,
                'reference' => $reference,
                'order_id' => $orderId,
                'deeplink' => config('services.mobile.wallet_deeplink', 'maribsrv://wallet'),
                'idempotency_key' => $transaction->idempotency_key,
                'created_at' => optional($transaction->created_at)->toIso8601String(),
            ],
        ];



        if (!empty($meta)) {
            $data['data']['meta'] = $meta;
        }

        return $data;

    }

    private function formatCurrencyLabel(string $currency): string
    {
        $normalized = strtoupper(trim($currency));
        $map = [
            'YER' => 'ريال يمني',
            'SAR' => 'ريال سعودي',
            'USD' => 'دولار أمريكي',
            'AED' => 'درهم إماراتي',
        ];

        return $map[$normalized] ?? $normalized;
    }

    private function formatReferenceText(?string $reference): string
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return '';
        }

        $cleanReference = $this->normalizeReferenceForNotification($reference);
        if ($cleanReference === '') {
            return '';
        }

        return "رقم المرجع: {$cleanReference}.";
    }

    private function normalizeReferenceForNotification(string $reference): string
    {
        if (str_contains($reference, 'wallet:')) {
            $parts = array_values(array_filter(explode(':', $reference)));
            return $parts !== [] ? end($parts) : $reference;
        }

        return $reference;
    }

    protected function sendWalletNotification(User $user, WalletTransaction $transaction): void
    {
        $payload = $this->buildWalletNotificationPayload($transaction);
        $deeplink = (string) ($payload['data']['deeplink'] ?? config('services.mobile.wallet_deeplink', 'maribsrv://wallet'));

        try {
            app(NotificationDispatchService::class)->dispatch(
                new NotificationIntent(
                    userId: $user->getKey(),
                    type: NotificationType::WalletAlert,
                    title: $payload['title'] ?? __('Wallet updated'),
                    body: $payload['body'] ?? '',
                    deeplink: $deeplink,
                    entity: 'wallet_transaction',
                    entityId: $transaction->getKey(),
                    data: $payload['data'] ?? [],
                    meta: [
                        'wallet_transaction_id' => $transaction->getKey(),
                        'wallet_account_id' => $transaction->wallet_account_id,
                    ],
                ),
            );
        } catch (Throwable $exception) {
            Log::error('WalletService: Failed to send wallet notification', [
                'error' => $exception->getMessage(),
                'exception_class' => get_class($exception),
            ]);
        }

    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, string>
     */
    protected function sanitizeMetadataForNotification($meta): array
    {
        if (!is_array($meta)) {
            return [];
        }

        $scalarKeys = [
            'context',
            'order_id',
            'manual_payment_request_id',
            'payment_transaction_id',
            'package_id',
            'reference',
            'source',
            'category',
        ];

        $sanitized = [];

        foreach ($scalarKeys as $key) {
            $value = Arr::get($meta, $key);

            if (is_scalar($value) && $value !== '') {
                $sanitized[$key] = Str::limit((string) $value, 190, '');
            }
        }

        $walletMeta = Arr::get($meta, 'wallet');

        if (is_array($walletMeta)) {
            foreach (['transaction_id', 'idempotency_key', 'reference'] as $walletKey) {
                $value = $walletMeta[$walletKey] ?? null;

                if (is_scalar($value) && $value !== '') {
                    $sanitized['wallet_' . $walletKey] = Str::limit((string) $value, 190, '');
                }
            }
        }

        return $sanitized;
    }

    /**
     * @return array<int, array{model: WalletUsageLimit, period_start: Carbon, period: string, projected: float}>
     */
    protected function prepareUsageLimitRecords(WalletAccount $walletAccount, string $direction, float $amount): array
    {
        $now = Carbon::now();

        $periods = [
            'daily' => $now->copy()->startOfDay(),
            'monthly' => $now->copy()->startOfMonth(),
        ];

        $records = [];

        foreach ($periods as $period => $start) {
            $limit = $this->resolveLimit($direction, $period);

            $usage = WalletUsageLimit::query()
                ->where('wallet_account_id', $walletAccount->getKey())
                ->where('period', $period)
                ->where('period_start', $start->toDateString())
                ->lockForUpdate()
                ->first();

            if (!$usage) {
                $usage = new WalletUsageLimit([
                    'wallet_account_id' => $walletAccount->getKey(),
                    'period' => $period,
                    'period_start' => $start->copy(),
                    'total_credit' => 0,
                    'total_debit' => 0,
                ]);


                $totals = $this->resolveUsageTotalsFromTransactions($walletAccount, $period, $start);

                $usage->total_credit = $totals['credit'];
                $usage->total_debit = $totals['debit'];

            }

            $current = $direction === 'credit'
                ? (float) $usage->total_credit
                : (float) $usage->total_debit;

            $projected = round($current + $amount, 2);

            if ($this->limitsEnabled() && $limit !== null && $projected > (float) $limit) {
                throw new RuntimeException(sprintf('%s %s limit exceeded.', ucfirst($period), $direction));
            }

            $records[] = [
                'model' => $usage,
                'period_start' => $start,
                'period' => $period,
                'projected' => $projected,
                'wallet_account_id' => $walletAccount->getKey(),
            ];
        }

        return $records;
    }

    protected function resolveUsageTotalsFromTransactions(WalletAccount $walletAccount, string $period, Carbon $periodStart): array
    {
        $start = $periodStart->copy();
        $end = $period === 'daily'
            ? $start->copy()->addDay()
            : $start->copy()->addMonth();

        $totals = WalletTransaction::query()
            ->selectRaw('type, SUM(amount) as total_amount')
            ->where('wallet_account_id', $walletAccount->getKey())
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->groupBy('type')
            ->pluck('total_amount', 'type');

        return [
            'credit' => round((float) ($totals['credit'] ?? 0), 2),
            'debit' => round((float) ($totals['debit'] ?? 0), 2),
        ];
    }




    protected function persistUsageLimitRecords(array $records, string $direction): void
    {
        if (empty($records)) {
            return;
        }

        foreach ($records as $record) {
            /** @var WalletUsageLimit $usage */
            $usage = $record['model'];

            $usage->wallet_account_id = $usage->wallet_account_id ?? $record['wallet_account_id'];
            $usage->period = $record['period'];
            $usage->period_start = $record['period_start'];

            if ($direction === 'credit') {
                $usage->total_credit = $record['projected'];
            } else {
                $usage->total_debit = $record['projected'];
            }

            if ($usage->total_credit === null) {
                $usage->total_credit = 0;
            }

            if ($usage->total_debit === null) {
                $usage->total_debit = 0;
            }

            $usage->save();
        }
    }

    protected function recordAuditEvent(WalletTransaction $walletTransaction, mixed $auditOptions): void
    {
        if (!is_array($auditOptions)) {
            return;
        }

        $performedById = null;

        if (array_key_exists('performed_by', $auditOptions)) {
            $value = $auditOptions['performed_by'];

            if ($value instanceof User) {
                $performedById = $value->getKey();
            } elseif (is_numeric($value)) {
                $performedById = (int) $value;
            }
        }

        if ($performedById === null && array_key_exists('performed_by_id', $auditOptions) && is_numeric($auditOptions['performed_by_id'])) {
            $performedById = (int) $auditOptions['performed_by_id'];
        }

        $meta = $auditOptions['meta'] ?? null;

        if ($meta !== null && !is_array($meta)) {
            $meta = null;
        }

        WalletAudit::create([
            'wallet_transaction_id' => $walletTransaction->getKey(),
            'performed_by' => $performedById,
            'difference' => isset($auditOptions['difference']) ? round((float) $auditOptions['difference'], 2) : 0,
            'notes' => $auditOptions['notes'] ?? null,
            'meta' => $meta,
        ]);
    }

    protected function limitsEnabled(): bool
    {
        return (bool) config('wallet.limits.enabled', false);
    }

    protected function resolveLimit(string $direction, string $period): ?float
    {
        $limit = config(sprintf('wallet.limits.%s.%s', $direction, $period));

        if ($limit === null || $limit === '') {
            return null;
        }

        return (float) $limit;
        
    }
}
