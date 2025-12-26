@extends('layouts.main')

@section('css')
    <style>
        .wallet-detail-page {
            background: linear-gradient(180deg, rgba(13, 110, 253, 0.07), rgba(13, 110, 253, 0.02));
            border: 1px solid rgba(15, 23, 42, 0.06);
            border-radius: 1.25rem;
            color: #212529;
            padding: 1.25rem;
        }
        .wallet-detail-shell {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .wallet-detail-hero {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .wallet-detail-title {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
        }
        .wallet-detail-subtitle {
            margin: 0.35rem 0 0;
            font-size: 0.9rem;
            color: #6c757d;
        }
        .wallet-detail-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .wallet-detail-summary {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 1rem;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }
        .wallet-detail-summary-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .wallet-detail-user {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .wallet-detail-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: #f59f00;
            background: rgba(245, 159, 0, 0.12);
        }
        .wallet-detail-balance {
            text-align: end;
            margin-inline-start: auto;
        }
        .wallet-detail-stats {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.75rem;
            overflow-x: auto;
            padding-bottom: 0.25rem;
            -webkit-overflow-scrolling: touch;
        }
        .metric-card {
            flex: 0 0 220px;
            background: #ffffff;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 0.9rem;
            padding: 0.85rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
            min-height: 76px;
        }
        .metric-icon {
            width: 40px;
            height: 40px;
            border-radius: 0.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: #0d6efd;
            background: rgba(13, 110, 253, 0.12);
        }
        .metric-card.credit .metric-icon {
            color: #198754;
            background: rgba(25, 135, 84, 0.12);
        }
        .metric-card.debit .metric-icon {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.12);
        }
        .metric-card.activity .metric-icon {
            color: #b58100;
            background: rgba(255, 193, 7, 0.18);
        }
        .metric-label {
            font-size: 0.78rem;
            color: #6c757d;
            font-weight: 600;
        }
        .metric-value {
            font-size: 1.05rem;
            font-weight: 700;
            color: #212529;
        }
        .wallet-detail-filters {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 1rem;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }
        .wallet-detail-filters .card-body {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            padding: 1.1rem;
        }
        .filters-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .filters-title {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
        }
        .filters-hint {
            margin: 0.2rem 0 0;
            font-size: 0.82rem;
            color: #6c757d;
        }
        .filters-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 0.75rem;
        }
        .filters-row .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: #6c757d;
        }
        .filters-row .form-select {
            min-width: 200px;
            border-radius: 0.75rem;
            border-color: rgba(15, 23, 42, 0.12);
        }
        .filters-row .btn {
            border-radius: 0.75rem;
        }
        .wallet-detail-table {
            border-radius: 1rem;
            border: 1px solid rgba(15, 23, 42, 0.08);
            margin-bottom: 0;
            overflow: hidden;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
        }
        .wallet-detail-table .card-header {
            background: #f8f9fb;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            padding: 0.9rem 1.1rem;
        }
        .wallet-detail-table .card-body {
            padding: 1.15rem;
        }
        .table-title {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
        }
        .table-hint {
            margin: 0.2rem 0 0;
            font-size: 0.82rem;
            color: #6c757d;
        }
        .wallet-detail-table .table thead th {
            background: #f8f9fa;
            color: #212529;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            padding: 0.85rem 1rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .wallet-detail-table .table tbody td {
            padding: 0.85rem 1rem;
        }
        .wallet-detail-table .table tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.04);
        }
        .wallet-detail-table .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: rgba(15, 23, 42, 0.02);
        }
        .wallet-transaction-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }
        .wallet-transaction-meta {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .wallet-counterparty {
            display: flex;
            flex-direction: column;
        }
        .wallet-counterparty small {
            color: #6c757d;
        }
        .wallet-pagination .pagination {
            flex-wrap: wrap;
            gap: 0.25rem;
        }
        .wallet-pagination .page-item .page-link {
            padding: 0.35rem 0.75rem;
            font-size: .875rem;
            border-radius: 0.75rem;
            min-width: 2.25rem;
            text-align: center;
        }
        .wallet-pagination .page-item .page-link svg,
        .wallet-pagination .page-item .page-link .fi {
            width: 1rem;
            height: 1rem;
        }

        @media (max-width: 768px) {
            .wallet-detail-page {
                padding: 1rem;
            }
            .wallet-detail-balance {
                text-align: start;
                margin-inline-start: 0;
            }
            .filters-row .form-select {
                min-width: 0;
                width: 100%;
            }
        }
    </style>
@endsection

@section('title')
    {{ __('Wallet for :name', ['name' => $user->name]) }}
@endsection

@push('modals')
    <div class="modal fade" id="manualCreditModal" tabindex="-1" aria-labelledby="manualCreditModalLabel"
         aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="manualCreditModalLabel">{{ __('Manual Deposit') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="manual-credit-form-tab" data-bs-toggle="tab"
                                    data-bs-target="#manual-credit-form" type="button" role="tab">
                                <i class="bi bi-plus-circle me-1"></i>{{ __('New Deposit') }}
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="manual-credit-history-tab" data-bs-toggle="tab"
                                    data-bs-target="#manual-credit-history" type="button" role="tab">
                                <i class="bi bi-clock-history me-1"></i>{{ __('History') }}
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="manual-credit-form" role="tabpanel"
                             aria-labelledby="manual-credit-form-tab">
                            <form method="post" action="{{ route('wallet.credit', $user) }}" class="needs-validation" novalidate>
                                @csrf
                                <div class="mb-3">
                                    <label for="modal_amount" class="form-label">{{ __('Amount') }}</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0.01" class="form-control" id="modal_amount" name="amount"
                                               value="{{ old('amount') }}" required placeholder="0.00">
                                        <span class="input-group-text">{{ $currency }}</span>
                                    </div>
                                    @error('amount')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label for="modal_operation_reference" class="form-label">{{ __('Operation reference') }}</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="modal_operation_reference" name="operation_reference"
                                               value="{{ old('operation_reference', $manualCreditReference) }}" readonly>
                                        <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                    </div>
                                    <div class="form-text">{{ __('Reference numbers are generated sequentially to avoid duplication.') }}</div>
                                    @error('operation_reference')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label for="modal_notes" class="form-label">{{ __('Administrative notes') }}</label>
                                    <textarea class="form-control" id="modal_notes" name="notes" rows="3" maxlength="500"
                                              placeholder="{{ __('Optional internal notes for reference.') }}">{{ old('notes') }}</textarea>
                                    @error('notes')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-1"></i>{{ __('Credit Wallet') }}
                                </button>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="manual-credit-history" role="tabpanel"
                             aria-labelledby="manual-credit-history-tab">
                            @if($manualCreditEntries->isEmpty())
                                <p class="text-muted mb-0">{{ __('No manual deposits have been recorded yet.') }}</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                        <tr>
                                            <th>{{ __('Reference') }}</th>
                                            <th class="text-end">{{ __('Amount') }}</th>
                                            <th>{{ __('Created At') }}</th>
                                            <th>{{ __('Notes') }}</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($manualCreditEntries as $entry)
                                            <tr>
                                                <td>{{ data_get($entry->meta, 'operation_reference') ?? $entry->getKey() }}</td>
                                                <td class="text-end text-success">+{{ number_format((float) $entry->amount, 2) }} {{ $currency }}</td>
                                                <td>
                                                    <div>{{ optional($entry->created_at)->format('Y-m-d H:i') }}</div>
                                                    <small class="text-muted">{{ optional($entry->created_at)->diffForHumans() }}</small>
                                                </td>
                                                <td>
                                                    {{ data_get($entry->meta, 'notes') ?? '—' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-bs-tab-target]').forEach(function (trigger) {
                trigger.addEventListener('click', function () {
                    var targetSelector = this.getAttribute('data-bs-tab-target');
                    if (!targetSelector) {
                        return;
                    }
                    var tabTrigger = document.querySelector('[data-bs-toggle="tab"][data-bs-target=\"' + targetSelector + '\"]');
                    if (tabTrigger) {
                        var tabInstance = bootstrap.Tab.getOrCreateInstance(tabTrigger);
                        tabInstance.show();
                    }
                });
            });
        @if ($errors->has('amount') || $errors->has('operation_reference') || $errors->has('notes'))
            var modalElement = document.getElementById('manualCreditModal');
            if (modalElement) {
                var modal = new bootstrap.Modal(modalElement);
                modal.show();
            }
        @endif
        });
    </script>
@endpush

@section('page-title')
    <div class="page-title">
        <div class="row align-items-center">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4 class="mb-0">@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first text-md-end">
                <nav aria-label="breadcrumb" class="breadcrumb-header">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('wallet.index') }}">{{ __('Wallet Accounts') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $user->name }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section wallet-detail-page">
        <div class="wallet-detail-shell">
            <div class="wallet-detail-hero">
                <div>
                    <h5 class="wallet-detail-title">@yield('title')</h5>
                    <p class="wallet-detail-subtitle">{{ __('Wallet Overview') }}</p>
                </div>
                <div class="wallet-detail-actions">
                    <button type="button"
                            class="btn btn-primary d-flex align-items-center gap-2"
                            data-bs-toggle="modal"
                            data-bs-target="#manualCreditModal"
                            data-bs-tab-target="#manual-credit-form">
                        <i class="bi bi-cash-stack"></i>
                        <span>{{ __('New Deposit') }}</span>
                    </button>
                    <button type="button"
                            class="btn btn-outline-secondary d-flex align-items-center gap-2"
                            data-bs-toggle="modal"
                            data-bs-target="#manualCreditModal"
                            data-bs-tab-target="#manual-credit-history">
                        <i class="bi bi-clock-history"></i>
                        <span>{{ __('History') }}</span>
                    </button>
                </div>
            </div>

            <div class="card wallet-detail-summary">
                <div class="card-body">
                    <div class="wallet-detail-summary-row">
                        <div class="wallet-detail-user">
                            <span class="wallet-detail-avatar">
                                <i class="bi bi-wallet2"></i>
                            </span>
                            <div>
                                <p class="text-muted mb-1">{{ __('محفظة المستخدم') }}</p>
                                <h4 class="mb-0">{{ $user->name }}</h4>
                                <small class="text-muted">{{ $user->email }}</small>
                            </div>
                        </div>
                        <div class="wallet-detail-balance">
                            <p class="text-muted mb-1">{{ __('الرصيد الحالي') }}</p>
                            <h2 class="fw-bold mb-2">{{ number_format((float) $walletAccount->balance, 2) }} {{ $currency }}</h2>
                            <span class="badge bg-primary-subtle text-primary">{{ __('محّث حتى') }} {{ now()->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wallet-detail-stats">
                <div class="metric-card">
                    <span class="metric-icon"><i class="bi bi-list-check"></i></span>
                    <div>
                        <div class="metric-label">{{ __('إجمالي الحركات') }}</div>
                        <div class="metric-value">{{ number_format($walletMetrics['total_transactions']) }}</div>
                    </div>
                </div>
                <div class="metric-card credit">
                    <span class="metric-icon"><i class="bi bi-arrow-down-circle"></i></span>
                    <div>
                        <div class="metric-label">{{ __('إجمالي الإيداعات') }}</div>
                        <div class="metric-value">{{ number_format((float) $walletMetrics['total_credits'], 2) }} {{ $currency }}</div>
                    </div>
                </div>
                <div class="metric-card debit">
                    <span class="metric-icon"><i class="bi bi-arrow-up-right-circle"></i></span>
                    <div>
                        <div class="metric-label">{{ __('إجمالي الخصومات') }}</div>
                        <div class="metric-value">{{ number_format((float) $walletMetrics['total_debits'], 2) }} {{ $currency }}</div>
                    </div>
                </div>
                <div class="metric-card activity">
                    <span class="metric-icon"><i class="bi bi-clock-history"></i></span>
                    <div>
                        <div class="metric-label">{{ __('آخر عملية') }}</div>
                        <div class="metric-value">{{ optional($walletMetrics['last_activity'])->diffForHumans() ?? __('لا يوجد سجل بعد') }}</div>
                    </div>
                </div>
            </div>

            <div class="card wallet-detail-filters">
                <div class="card-body">
                    <div class="filters-header">
                        <div>
                            <h6 class="filters-title">{{ __('سجل الحركات المالية') }}</h6>
                            <p class="filters-hint">{{ __('يمكنك فلترة العمليات بحسب نوعها ومراجعة التفاصيل الدقيقة لكل عملية.') }}</p>
                        </div>
                    </div>
                    <form method="get" class="filters-row">
                        <div>
                            <label for="filter" class="form-label mb-1">{{ __('نوع الحركة') }}</label>
                            <select id="filter" name="filter" class="form-select" onchange="this.form.submit()">
                                @foreach($filters as $filterOption)
                                    <option value="{{ $filterOption }}" @selected($appliedFilter === $filterOption)>
                                        {{ __('wallet.filters.' . $filterOption) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <a href="{{ route('wallet.show', ['user' => $user->getKey()]) }}" class="btn btn-outline-secondary">
                                {{ __('Reset') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card wallet-detail-table">
                <div class="card-header">
                    <div>
                        <h6 class="table-title">{{ __('سجل الحركات المالية') }}</h6>
                        <p class="table-hint">{{ __('يمكنك فلترة العمليات بحسب نوعها ومراجعة التفاصيل الدقيقة لكل عملية.') }}</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="wallet-ledger-transactions" role="tabpanel"
                             aria-labelledby="wallet-ledger-transactions-tab">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>{{ __('المرجع') }}</th>
                                        <th>{{ __('Operation') }}</th>
                                        <th>{{ __('Transfer party') }}</th>
                                        <th class="text-end">{{ __('البلغ') }}</th>
                                        <th class="text-end">{{ __('الرصيد بعد العملية') }}</th>
                                        <th>{{ __('تفاصيل إضافية') }}</th>
                                        <th>{{ __('تاريخ التنفيذ') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @php
                                        $rowNumber = ($transactions->currentPage() - 1) * $transactions->perPage();
                                    @endphp
                                    @forelse($transactions as $transaction)
                                        @php
                                            $meta = is_array($transaction->meta) ? $transaction->meta : [];
                                            $metaReason = data_get($meta, 'reason');
                                            $metaContext = data_get($meta, 'context');
                                            $operationReference = data_get($meta, 'operation_reference');
                                            $notes = data_get($meta, 'notes');
                                            $transferReference = data_get($meta, 'reference')
                                                ?? data_get($meta, 'transfer_reference')
                                                ?? data_get($meta, 'wallet_reference');
                                            $transferKey = data_get($meta, 'transfer_key');
                                            $clientTag = data_get($meta, 'client_tag');
                                            $counterpartyName = trim((string) data_get($meta, 'counterparty.name', ''));
                                            $counterpartyId = data_get($meta, 'counterparty.id');
                                            $transferDirection = (string) data_get($meta, 'direction');
                                            $isTransfer = $metaReason === 'wallet_transfer' || $metaContext === 'wallet_transfer';
                                            $isRefund = in_array($metaReason, ['refund', 'wallet_refund'], true);
                                            $isAdminCredit = $metaReason === 'admin_manual_credit';
                                            $isTopUp = $transaction->manual_payment_request_id
                                                || $metaReason === \App\Models\ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP
                                                || $metaReason === 'wallet_top_up';
                                            $categoryLabel = 'Other';
                                            $categoryBadgeClass = 'bg-secondary';
                                            if ($isTransfer) {
                                                $categoryLabel = 'Transfer';
                                                $categoryBadgeClass = 'bg-info';
                                            } elseif ($isRefund) {
                                                $categoryLabel = 'Refund';
                                                $categoryBadgeClass = 'bg-warning text-dark';
                                            } elseif ($isAdminCredit) {
                                                $categoryLabel = 'Manual credit';
                                                $categoryBadgeClass = 'bg-primary';
                                            } elseif ($isTopUp) {
                                                $categoryLabel = 'Top-up';
                                                $categoryBadgeClass = 'bg-success';
                                            } elseif ($transaction->type === 'debit') {
                                                $categoryLabel = 'Purchase';
                                                $categoryBadgeClass = 'bg-danger';
                                            } elseif ($transaction->type === 'credit') {
                                                $categoryLabel = 'Credit';
                                                $categoryBadgeClass = 'bg-success';
                                            }
                                            $typeLabel = $transaction->type === 'credit' ? 'Credit' : 'Debit';
                                            $typeBadgeClass = $transaction->type === 'credit' ? 'bg-success' : 'bg-danger';
                                            $transferSide = $transferDirection === 'incoming' ? 'From' : ($transferDirection === 'outgoing' ? 'To' : ($transaction->type === 'credit' ? 'From' : 'To'));
                                            $counterpartyLabel = $counterpartyName !== '' ? $counterpartyName : ($counterpartyId ? 'User #' . $counterpartyId : 'Unknown');
                                        @endphp
                                        <tr>
                                            <td class="text-center fw-semibold">{{ ++$rowNumber }}</td>
                                            <td>
                                                <div class="fw-semibold">#{{ $transaction->getKey() }}</div>
                                                @if($operationReference)
                                                    <div class="small text-muted">{{ $operationReference }}</div>
                                                @elseif($transferReference)
                                                    <div class="small text-muted">{{ $transferReference }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="wallet-transaction-badges">
                                                    <span class="badge {{ $categoryBadgeClass }}">{{ $categoryLabel }}</span>
                                                    <span class="badge {{ $typeBadgeClass }}">{{ $typeLabel }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                @if($isTransfer)
                                                    <div class="wallet-counterparty">
                                                        <span class="fw-semibold">{{ $counterpartyLabel }}</span>
                                                        <small>{{ $transferSide }}{{ $counterpartyId ? ' #' . $counterpartyId : '' }}</small>
                                                    </div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <span class="fw-semibold {{ $transaction->type === 'credit' ? 'text-success' : 'text-danger' }}">
                                                    {{ number_format((float) $transaction->amount, 2) }} {{ $currency }}
                                                </span>
                                            </td>
                                            <td class="text-end">{{ number_format((float) $transaction->balance_after, 2) }} {{ $currency }}</td>
                                            <td>
                                                <div class="small text-muted wallet-transaction-meta">
                                                    @if($transaction->manualPaymentRequest)
                                                        @php
                                                            $mprRef = \App\Support\Payments\ReferencePresenter::forManualRequest(
                                                                $transaction->manualPaymentRequest,
                                                                $transaction->paymentTransaction ?? null
                                                            );
                                                        @endphp
                                                        <div>
                                                            <i class="bi bi-file-earmark-text me-1"></i>
                                                            {{ __('?�?"?� ?�???� ???�?^??') }}: {{ $mprRef ?? $transaction->manualPaymentRequest->getKey() }}
                                                        </div>
                                                    @endif
                                                    @if($transaction->paymentTransaction)
                                                        @php
                                                            $txRef = \App\Support\Payments\ReferencePresenter::forTransaction($transaction->paymentTransaction);
                                                        @endphp
                                                        <div>
                                                            <i class="bi bi-credit-card me-1"></i>
                                                            {{ __('?�?.?"???� ?�???�') }}: {{ $txRef ?? $transaction->paymentTransaction->getKey() }}
                                                        </div>
                                                    @endif
                                                    @if($isTransfer)
                                                        <div>
                                                            <i class="bi bi-arrow-left-right me-1"></i>
                                                            {{ $transferSide }} {{ $counterpartyLabel }}
                                                        </div>
                                                        @if($transferReference)
                                                            <div>
                                                                <i class="bi bi-hash me-1"></i>
                                                                {{ __('Reference') }}: {{ $transferReference }}
                                                            </div>
                                                        @endif
                                                        @if($transferKey)
                                                            <div>
                                                                <i class="bi bi-key me-1"></i>
                                                                {{ __('Transfer key') }}: {{ $transferKey }}
                                                            </div>
                                                        @endif
                                                        @if($clientTag)
                                                            <div>
                                                                <i class="bi bi-tag me-1"></i>
                                                                {{ __('Client tag') }}: {{ $clientTag }}
                                                            </div>
                                                        @endif
                                                    @endif
                                                    @if($metaReason)
                                                        <div>
                                                            <i class="bi bi-info-circle me-1"></i>
                                                            {{ __('Reason') }}: {{ \Illuminate\Support\Str::headline($metaReason) }}
                                                        </div>
                                                    @endif
                                                    @if($notes)
                                                        <div>
                                                            <i class="bi bi-chat-text me-1"></i>
                                                            {{ __('Notes') }}: {{ $notes }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small">{{ optional($transaction->created_at)->format('Y-m-d H:i') }}</div>
                                                <div class="text-muted small">{{ optional($transaction->created_at)->diffForHumans() }}</div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">
                                                <i class="bi bi-inboxes display-6 d-block mb-2"></i>
                                                {{ __('لا توجد حركات مطابقة للفلتر الحالي.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="wallet-ledger-manual" role="tabpanel"
                             aria-labelledby="wallet-ledger-manual-tab">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>{{ __('Reference') }}</th>
                                        <th class="text-end">{{ __('Amount') }}</th>
                                        <th>{{ __('Created At') }}</th>
                                        <th>{{ __('Notes') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @php
                                        $manualRow = 0;
                                    @endphp
                                    @forelse($manualCreditEntries as $entry)
                                        <tr>
                                            <td class="text-center fw-semibold">{{ ++$manualRow }}</td>
                                            <td>{{ data_get($entry->meta, 'operation_reference') ?? $entry->getKey() }}</td>
                                            <td class="text-end text-success">+{{ number_format((float) $entry->amount, 2) }} {{ $currency }}</td>
                                            <td>
                                                <div>{{ optional($entry->created_at)->format('Y-m-d H:i') }}</div>
                                                <small class="text-muted">{{ optional($entry->created_at)->diffForHumans() }}</small>
                                            </td>
                                            <td>{{ data_get($entry->meta, 'notes') ?? __('Not available') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="bi bi-journal-x display-6 d-block mb-2"></i>
                                                {{ __('No manual deposits have been recorded yet.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                @if($transactions->hasPages())
                    <div class="card-footer bg-white border-0 wallet-pagination">
                        {{ $transactions->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection




 = 
                                            $meta = is_array($transaction->meta) ? $transaction->meta : [];
                                            $metaReason = data_get($meta, 'reason');
                                            $metaContext = data_get($meta, 'context');
                                            $operationReference = data_get($meta, 'operation_reference');
                                            $notes = data_get($meta, 'notes');
                                            $transferReference = data_get($meta, 'reference')
                                                ?? data_get($meta, 'transfer_reference')
                                                ?? data_get($meta, 'wallet_reference');
                                            $transferKey = data_get($meta, 'transfer_key');
                                            $clientTag = data_get($meta, 'client_tag');
                                            $counterpartyName = trim((string) data_get($meta, 'counterparty.name', ''));
                                            $counterpartyId = data_get($meta, 'counterparty.id');
                                            $transferDirection = (string) data_get($meta, 'direction');
                                            $isTransfer = $metaReason === 'wallet_transfer' || $metaContext === 'wallet_transfer';
                                            $isRefund = in_array($metaReason, ['refund', 'wallet_refund'], true);
                                            $isAdminCredit = $metaReason === 'admin_manual_credit';
                                            $isTopUp = $transaction->manual_payment_request_id
                                                || $metaReason === \App\Models\ManualPaymentRequest::PAYABLE_TYPE_WALLET_TOP_UP
                                                || $metaReason === 'wallet_top_up';
                                            $categoryLabel = 'Other';
                                            $categoryBadgeClass = 'bg-secondary';
                                            if ($isTransfer) {
                                                $categoryLabel = 'Transfer';
                                                $categoryBadgeClass = 'bg-info';
                                            } elseif ($isRefund) {
                                                $categoryLabel = 'Refund';
                                                $categoryBadgeClass = 'bg-warning text-dark';
                                            } elseif ($isAdminCredit) {
                                                $categoryLabel = 'Manual credit';
                                                $categoryBadgeClass = 'bg-primary';
                                            } elseif ($isTopUp) {
                                                $categoryLabel = 'Top-up';
                                                $categoryBadgeClass = 'bg-success';
                                            } elseif ($transaction->type === 'debit') {
                                                $categoryLabel = 'Purchase';
                                                $categoryBadgeClass = 'bg-danger';
                                            } elseif ($transaction->type === 'credit') {
                                                $categoryLabel = 'Credit';
                                                $categoryBadgeClass = 'bg-success';
                                            }
                                            $typeLabel = $transaction->type === 'credit' ? 'Credit' : 'Debit';
                                            $typeBadgeClass = $transaction->type === 'credit' ? 'bg-success' : 'bg-danger';
                                            $transferSide = $transferDirection === 'incoming' ? 'From' : ($transferDirection === 'outgoing' ? 'To' : ($transaction->type === 'credit' ? 'From' : 'To'));
                                            $counterpartyLabel = $counterpartyName !== '' ? $counterpartyName : ($counterpartyId ? 'User #' . $counterpartyId : 'Unknown');
                                        @endphp
                                        <tr>
                                            <td class="text-center fw-semibold">{{ ++$rowNumber }}</td>
                                            <td>
                                                <div class="fw-semibold">#{{ $transaction->getKey() }}</div>
                                                @if($operationReference)
                                                    <div class="small text-muted">{{ $operationReference }}</div>
                                                @elseif($transferReference)
                                                    <div class="small text-muted">{{ $transferReference }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="wallet-transaction-badges">
                                                    <span class="badge {{ $categoryBadgeClass }}">{{ $categoryLabel }}</span>
                                                    <span class="badge {{ $typeBadgeClass }}">{{ $typeLabel }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                @if($isTransfer)
                                                    <div class="wallet-counterparty">
                                                        <span class="fw-semibold">{{ $counterpartyLabel }}</span>
                                                        <small>{{ $transferSide }}{{ $counterpartyId ? ' #' . $counterpartyId : '' }}</small>
                                                    </div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <span class="fw-semibold {{ $transaction->type === 'credit' ? 'text-success' : 'text-danger' }}">
                                                    {{ number_format((float) $transaction->amount, 2) }} {{ $currency }}
                                                </span>
                                            </td>
                                            <td class="text-end">{{ number_format((float) $transaction->balance_after, 2) }} {{ $currency }}</td>
                                            <td>
                                                <div class="small text-muted wallet-transaction-meta">
                                                    @if($transaction->manualPaymentRequest)
                                                        @php
                                                            $mprRef = \App\Support\Payments\ReferencePresenter::forManualRequest(
                                                                $transaction->manualPaymentRequest,
                                                                $transaction->paymentTransaction ?? null
                                                            );
                                                        @endphp
                                                        <div>
                                                            <i class="bi bi-file-earmark-text me-1"></i>
                                                            {{ __('?�?"?� ?�???� ???�?^??') }}: {{ $mprRef ?? $transaction->manualPaymentRequest->getKey() }}
                                                        </div>
                                                    @endif
                                                    @if($transaction->paymentTransaction)
                                                        @php
                                                            $txRef = \App\Support\Payments\ReferencePresenter::forTransaction($transaction->paymentTransaction);
                                                        @endphp
                                                        <div>
                                                            <i class="bi bi-credit-card me-1"></i>
                                                            {{ __('?�?.?"???� ?�???�') }}: {{ $txRef ?? $transaction->paymentTransaction->getKey() }}
                                                        </div>
                                                    @endif
                                                    @if($isTransfer)
                                                        <div>
                                                            <i class="bi bi-arrow-left-right me-1"></i>
                                                            {{ $transferSide }} {{ $counterpartyLabel }}
                                                        </div>
                                                        @if($transferReference)
                                                            <div>
                                                                <i class="bi bi-hash me-1"></i>
                                                                {{ __('Reference') }}: {{ $transferReference }}
                                                            </div>
                                                        @endif
                                                        @if($transferKey)
                                                            <div>
                                                                <i class="bi bi-key me-1"></i>
                                                                {{ __('Transfer key') }}: {{ $transferKey }}
                                                            </div>
                                                        @endif
                                                        @if($clientTag)
                                                            <div>
                                                                <i class="bi bi-tag me-1"></i>
                                                                {{ __('Client tag') }}: {{ $clientTag }}
                                                            </div>
                                                        @endif
                                                    @endif
                                                    @if($metaReason)
                                                        <div>
                                                            <i class="bi bi-info-circle me-1"></i>
                                                            {{ __('Reason') }}: {{ \Illuminate\Support\Str::headline($metaReason) }}
                                                        </div>
                                                    @endif
                                                    @if($notes)
                                                        <div>
                                                            <i class="bi bi-chat-text me-1"></i>
                                                            {{ __('Notes') }}: {{ $notes }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small">{{ optional($transaction->created_at)->format('Y-m-d H:i') }}</div>
                                                <div class="text-muted small">{{ optional($transaction->created_at)->diffForHumans() }}</div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">
                                                <i class="bi bi-inboxes display-6 d-block mb-2"></i>
                                                {{ __('لا توجد حركات مطابقة للفلتر الحالي.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="wallet-ledger-manual" role="tabpanel"
                             aria-labelledby="wallet-ledger-manual-tab">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>{{ __('Reference') }}</th>
                                        <th class="text-end">{{ __('Amount') }}</th>
                                        <th>{{ __('Created At') }}</th>
                                        <th>{{ __('Notes') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @php
                                        $manualRow = 0;
                                    @endphp
                                    @forelse($manualCreditEntries as $entry)
                                        <tr>
                                            <td class="text-center fw-semibold">{{ ++$manualRow }}</td>
                                            <td>{{ data_get($entry->meta, 'operation_reference') ?? $entry->getKey() }}</td>
                                            <td class="text-end text-success">+{{ number_format((float) $entry->amount, 2) }} {{ $currency }}</td>
                                            <td>
                                                <div>{{ optional($entry->created_at)->format('Y-m-d H:i') }}</div>
                                                <small class="text-muted">{{ optional($entry->created_at)->diffForHumans() }}</small>
                                            </td>
                                            <td>{{ data_get($entry->meta, 'notes') ?? __('Not available') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="bi bi-journal-x display-6 d-block mb-2"></i>
                                                {{ __('No manual deposits have been recorded yet.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                @if($transactions->hasPages())
                    <div class="card-footer bg-white border-0 wallet-pagination">
                        {{ $transactions->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection




