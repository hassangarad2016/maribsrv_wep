<?php

namespace App\Http\Controllers;

use App\Models\ManualPaymentRequest;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Services\ResponseService;
use App\Services\WalletService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class WalletAdminController extends Controller
{
    private const FILTERS = [
        'all',
        'top-ups',
        'payments',
        'transfers',
        'refunds',
    ];

    public function __construct(private readonly WalletService $walletService)
    {
    }

    public function index(Request $request): View
    {
        ResponseService::noPermissionThenRedirect('wallet-manage');

        $search = trim((string) $request->get('search', ''));

        $baseQuery = WalletAccount::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->whereHas('user', function (Builder $builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                });
            });

        $accountsQuery = (clone $baseQuery)
            ->with('user')
            ->orderByDesc('updated_at');

        /** @var LengthAwarePaginator $accounts */
        $accounts = $accountsQuery->paginate(15)->withQueryString();

        $totalsQuery = clone $baseQuery;

        $totalBalance = (float) $totalsQuery->sum('balance');
        $accountCount = (int) $totalsQuery->count();
        $lastUpdatedAtValue = $totalsQuery->max('updated_at');
        $lastUpdatedAt = $lastUpdatedAtValue ? Carbon::parse($lastUpdatedAtValue) : null;
        $recentlyUpdatedCount = (clone $baseQuery)
            ->where('updated_at', '>=', Carbon::now()->startOfDay())
            ->count();


        return view('wallet.index', [
            'accounts' => $accounts,
            'search' => $search,
            'totalBalance' => $totalBalance,
            'accountCount' => $accountCount,
            'lastUpdatedAt' => $lastUpdatedAt,
            'recentlyUpdatedCount' => $recentlyUpdatedCount,


            'currency' => strtoupper(config('app.currency', 'SAR')),
        ]);
    }

    public function datatable(Request $request, User $user)
    {
        try {
            ResponseService::noPermissionThenSendJson('wallet-manage');

            $walletAccount = WalletAccount::query()->firstOrCreate(
                ['user_id' => $user->getKey()],
                ['balance' => 0]
            );

            $offset = (int) $request->input('offset', 0);
            $limitParam = $request->input('limit', 50);

            if (is_string($limitParam) && strtolower($limitParam) === 'all') {
                $limit = -1;
            } else {
                $limit = (int) $limitParam;
                if ($limit === 0) {
                    $limit = 50;
                }
            }

            $sort = (string) $request->input('sort', 'id');
            $order = strtoupper((string) $request->input('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
            $filter = $this->resolveFilter($request->input('filter'));

            $query = WalletTransaction::query()
                ->where('wallet_account_id', $walletAccount->getKey());

            $this->applyWalletTransactionFilter($query, $filter);

            if (!empty($request->search)) {
                $search = trim((string) $request->search);
                $query->where(function (Builder $builder) use ($search) {
                    $builder->where('id', (int) $search)
                        ->orWhere('idempotency_key', 'like', "%{$search}%")
                        ->orWhere('meta->operation_reference', 'like', "%{$search}%")
                        ->orWhere('meta->reference', 'like', "%{$search}%")
                        ->orWhere('meta->transfer_reference', 'like', "%{$search}%")
                        ->orWhere('meta->wallet_reference', 'like', "%{$search}%")
                        ->orWhere('meta->transfer_key', 'like', "%{$search}%")
                        ->orWhere('meta->client_tag', 'like', "%{$search}%")
                        ->orWhere('meta->reason', 'like', "%{$search}%")
                        ->orWhere('meta->notes', 'like', "%{$search}%");
                });
            }

            $total = (clone $query)->count();

            $sortable = ['id', 'amount', 'balance_after', 'created_at', 'type'];
            if (!in_array($sort, $sortable, true)) {
                $sort = 'id';
            }

            if ($limit <= 0) {
                $rows = $query->orderBy($sort, $order)->get();
            } else {
                $rows = $query->orderBy($sort, $order)
                    ->skip($offset)
                    ->take($limit)
                    ->get();
            }

            $dataRows = [];

            foreach ($rows as $row) {
                $meta = is_array($row->meta) ? $row->meta : [];
                $reason = (string) data_get($meta, 'reason');
                $context = (string) data_get($meta, 'context');
                $transferDirection = (string) data_get($meta, 'direction');
                $counterpartyName = trim((string) data_get($meta, 'counterparty.name', ''));
                $counterpartyId = data_get($meta, 'counterparty.id');
                $operationReference = data_get($meta, 'operation_reference');
                $transferReference = data_get($meta, 'reference')
                    ?? data_get($meta, 'transfer_reference')
                    ?? data_get($meta, 'wallet_reference');

                $isTransfer = $reason === 'wallet_transfer' || $context === 'wallet_transfer';
                $isRefund = in_array($reason, ['refund', 'wallet_refund'], true);
                $isAdminCredit = $reason === 'admin_manual_credit';
                $isTopUp = $row->manual_payment_request_id
                    || $reason === ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP
                    || $reason === 'wallet_top_up';

                $categoryLabel = 'Other';
                if ($isTransfer) {
                    $categoryLabel = 'Transfer';
                } elseif ($isRefund) {
                    $categoryLabel = 'Refund';
                } elseif ($isAdminCredit) {
                    $categoryLabel = 'Manual credit';
                } elseif ($isTopUp) {
                    $categoryLabel = 'Top-up';
                } elseif ($row->type === 'debit') {
                    $categoryLabel = 'Purchase';
                } elseif ($row->type === 'credit') {
                    $categoryLabel = 'Credit';
                }

                $typeLabel = $row->type === 'credit' ? 'Credit' : 'Debit';
                $direction = $transferDirection !== '' ? $transferDirection : ($row->type === 'credit' ? 'incoming' : 'outgoing');
                $partyLabel = $counterpartyName !== '' ? $counterpartyName : ($counterpartyId ? 'User #' . $counterpartyId : null);

                $currency = strtoupper((string) ($row->currency ?? config('app.currency', 'SAR')));

                $dataRows[] = [
                    'id' => $row->getKey(),
                    'reference' => $operationReference ?: ($transferReference ?: '#' . $row->getKey()),
                    'operation_reference' => $operationReference,
                    'transfer_reference' => $transferReference,
                    'category_label' => $categoryLabel,
                    'type' => $row->type,
                    'type_label' => $typeLabel,
                    'direction' => $direction,
                    'party_label' => $partyLabel,
                    'party_id' => $counterpartyId,
                    'amount' => (float) $row->amount,
                    'balance_after' => (float) $row->balance_after,
                    'currency' => $currency,
                    'created_at' => optional($row->created_at)->toDateTimeString(),
                    'created_human' => optional($row->created_at)->format('Y-m-d H:i'),
                    'meta_reason' => $reason,
                    'notes' => data_get($meta, 'notes'),
                    'idempotency_key' => $row->idempotency_key,
                    'manual_payment_request_id' => $row->manual_payment_request_id,
                    'payment_transaction_id' => $row->payment_transaction_id,
                    'transfer_key' => data_get($meta, 'transfer_key'),
                    'client_tag' => data_get($meta, 'client_tag'),
                ];
            }

            return response()->json([
                'total' => $total,
                'rows' => $dataRows,
            ]);
        } catch (Throwable $throwable) {
            ResponseService::logErrorResponse($throwable, 'WalletAdminController -> datatable');
            ResponseService::errorResponse();
        }
    }

    public function show(Request $request, User $user): View
    {
        ResponseService::noPermissionThenRedirect('wallet-manage');

        $filter = $this->resolveFilter($request->get('filter'));

        $walletAccount = WalletAccount::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            ['balance' => 0]
        );

        $transactionsQuery = WalletTransaction::query()
            ->with([
                'manualPaymentRequest',
                'paymentTransaction.walletTransaction',
            ])
            ->where('wallet_account_id', $walletAccount->getKey())
            ->latest('created_at');

        $this->applyWalletTransactionFilter($transactionsQuery, $filter);

        /** @var LengthAwarePaginator $transactions */
        $transactions = $transactionsQuery
            ->paginate(15)
            ->appends(['filter' => $filter]);

        $latestTransaction = $walletAccount->transactions()
            ->latest('created_at')
            ->first();

        $transactionsBase = WalletTransaction::query()
            ->where('wallet_account_id', $walletAccount->getKey());

        $lastActivityValue = (clone $transactionsBase)->latest('created_at')->value('created_at');

        $walletMetrics = [
            'total_transactions' => (clone $transactionsBase)->count(),
            'total_credits' => (clone $transactionsBase)->where('type', 'credit')->sum('amount'),
            'total_debits' => (clone $transactionsBase)->where('type', 'debit')->sum('amount'),
            'last_activity' => $lastActivityValue ? Carbon::parse($lastActivityValue) : null,
        ];

        $manualCreditReference = $this->generateOperationReference();

        $manualCreditQuery = WalletTransaction::query()
            ->where('wallet_account_id', $walletAccount->getKey())
            ->where('meta->reason', 'admin_manual_credit');

        $manualCreditEntries = (clone $manualCreditQuery)
            ->latest('created_at')
            ->take(10)
            ->get();

        $manualCreditCount = (clone $manualCreditQuery)->count();
        $manualCreditTotal = (clone $manualCreditQuery)->sum('amount');
        $manualCreditLatest = (clone $manualCreditQuery)->latest('created_at')->first();

        return view('wallet.show', [
            'user' => $user,
            'walletAccount' => $walletAccount->fresh(),
            'transactions' => $transactions,
            'latestTransaction' => $latestTransaction,
            'filters' => self::FILTERS,
            'appliedFilter' => $filter,
            'currency' => strtoupper(config('app.currency', 'SAR')),
            'walletMetrics' => $walletMetrics,
            'manualCreditReference' => $manualCreditReference,
            'manualCreditEntries' => $manualCreditEntries,
            'manualCreditStats' => [
                'count' => $manualCreditCount,
                'total' => $manualCreditTotal,
                'last_reference' => data_get($manualCreditLatest?->meta, 'operation_reference'),
                'last_date' => $manualCreditLatest?->created_at,
            ],
        ]);
    }

    public function credit(Request $request, User $user): RedirectResponse
    {
        ResponseService::noPermissionThenRedirect('wallet-manage');

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'operation_reference' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'operation_reference' => __('Operation reference'),
            'notes' => __('Administrative notes'),
        ]);

        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $operationReference = trim((string) ($validated['operation_reference'] ?? ''));
        if ($operationReference === '') {
            $operationReference = $this->generateOperationReference();
        }
        $amount = (float) $validated['amount'];

        $idempotencyKey = sprintf(
            'wallet:admin-manual-credit:%d:%d:%s',
            Auth::id(),
            $user->getKey(),
            md5(Str::lower($operationReference))
        );

        try {
            $transaction = $this->walletService->credit($user, $idempotencyKey, $amount, [
                'meta' => array_filter([
                    'reason' => 'admin_manual_credit',
                    'operation_reference' => $operationReference,
                    'performed_by' => Auth::id(),
                    'notes' => $validated['notes'] ?? null,
                ]),
            ]);
        } catch (RuntimeException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'idempotency key')) {
                return redirect()
                    ->back()
                    ->withErrors([
                        'operation_reference' => __('This administrative operation was already processed.'),
                    ])
                    ->withInput();
            }

            return redirect()
                ->back()
                ->withErrors([
                    'amount' => $exception->getMessage(),
                ])
                ->withInput();
        } catch (Throwable $exception) {
            return redirect()
                ->back()
                ->withErrors([
                    'amount' => __('Failed to credit the wallet. Please try again.'),
                ])
                ->withInput();
        }

        return redirect()
            ->route('wallet.show', $user)
            ->with('success', __('Wallet credited successfully.'))
            ->with('wallet_transaction_id', $transaction->getKey());
    }

    private function resolveFilter(?string $filter): string
    {
        return in_array($filter, self::FILTERS, true) ? $filter : 'all';
    }

    private function applyWalletTransactionFilter(Builder $query, string $filter): void
    {
        switch ($filter) {
            case 'top-ups':
                $query->where('type', 'credit')
                    ->where(function (Builder $builder) {
                        $builder->whereNotNull('manual_payment_request_id')
                            ->orWhere('meta->reason', ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP)
                            ->orWhere('meta->reason', 'wallet_top_up')
                            ->orWhere('meta->reason', 'admin_manual_credit');
                    });
                break;
            case 'payments':
                $query->where('type', 'debit')
                    ->where(function (Builder $builder) {
                        $builder->whereNull('meta->reason')
                            ->orWhere('meta->reason', '!=', 'wallet_transfer');
                    });
                break;
            case 'transfers':
                $query->where(function (Builder $builder) {
                    $builder->where('meta->reason', 'wallet_transfer')
                        ->orWhere('meta->context', 'wallet_transfer');
                });
                break;
            case 'refunds':
                $query->where('type', 'credit')
                    ->where(function (Builder $builder) {
                        $builder->where('meta->reason', 'refund')
                            ->orWhere('meta->reason', 'wallet_refund');
                    });
                break;
            default:
                break;
        }
    }

    private function generateOperationReference(): string
    {
        $nextId = (int) WalletTransaction::max('id') + 1;
        return 'WDEP-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
    }
}
