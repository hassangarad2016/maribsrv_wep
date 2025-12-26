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
            flex-wrap: nowrap;
            gap: 0.5rem;
        }
        .wallet-detail-summary {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 1rem;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }
        .wallet-detail-summary-row {
            display: flex;
            flex-wrap: nowrap;
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
            flex-wrap: nowrap;
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
            flex-wrap: nowrap;
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
                .search-group {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0.5rem;
            margin-inline-start: auto;
        }
        .search-group .input-group {
            min-width: 240px;
            max-width: 320px;
        }
        .search-group .input-group-text {
            background: #f8f9fa;
            border-radius: 0.75rem 0 0 0.75rem;
            border-color: rgba(15, 23, 42, 0.12);
        }
        .search-group .form-control {
            height: 38px;
            font-size: 0.9rem;
            border-radius: 0 0.75rem 0.75rem 0;
            border-color: rgba(15, 23, 42, 0.12);
        }
        .search-group .btn {
            height: 38px;
            padding: 0 0.9rem;
            font-size: 0.85rem;
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
                .wallet-detail-table .table {
            margin-bottom: 0;
            table-layout: fixed;
        }
        .wallet-detail-table .table thead th,
        .wallet-detail-table .table tbody td {
            white-space: nowrap;
        }
        .wallet-detail-table .table tbody td {
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 220px;
        }
        .wallet-detail-table .fixed-table-toolbar {
            margin-bottom: 0.75rem;
        }
        .wallet-detail-table .fixed-table-toolbar .columns {
            display: inline-flex;
            align-items: center;
            gap: 0.15rem;
            background: #4b5563;
            border-radius: 0.75rem;
            padding: 0.25rem;
            box-shadow: 0 10px 18px rgba(15, 23, 42, 0.18);
        }
        .wallet-detail-table .fixed-table-toolbar .columns .btn,
        .wallet-detail-table .fixed-table-toolbar .columns .btn-group > .btn {
            background: transparent;
            border: 0;
            color: #ffffff;
            width: 36px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: none;
        }
        .wallet-detail-table .fixed-table-toolbar .columns .btn:hover,
        .wallet-detail-table .fixed-table-toolbar .columns .btn-group > .btn:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        .wallet-detail-table .fixed-table-toolbar .columns .dropdown-toggle::after {
            display: none;
        }
        .wallet-detail-table .fixed-table-toolbar .columns .btn i {
            font-size: 1rem;
        }
        #wallet_transactions_table {
            width: 100%;
        }
        .wallet-preview-grid {
            display: grid;
            gap: 0.75rem;
        }
        .wallet-preview-item {
            background: #f8f9fb;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 0.75rem;
            padding: 0.75rem;
        }
        .wallet-preview-label {
            font-size: 0.75rem;
            color: #6c757d;
        }
        .wallet-preview-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #0f172a;
        }
.wallet-transaction-badges {
            display: flex;
            flex-wrap: nowrap;
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
            flex-wrap: nowrap;
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

@push('scripts')
    <script>
        window.walletTransactionsTableIcons = {
            refresh: 'bi-arrow-clockwise',
            columns: 'bi-list-ul',
            export: 'bi-download'
        };

        function walletEscapeHtml(value) {
            if (value === null || value === undefined) {
                return '';
            }
            return String(value).replace(/[&<>"']/g, function (char) {
                return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'})[char];
            });
        }

        function walletReferenceFormatter(value, row) {
            var label = value || (row && row.id ? ('#' + row.id) : '-');
            return '<span class="badge bg-primary text-white fw-semibold px-3 py-2" title="' + walletEscapeHtml(label) + '">' + walletEscapeHtml(label) + '</span>';
        }

        function walletOperationFormatter(value, row) {
            var category = row && row.category_label ? row.category_label : 'Other';
            var type = row && row.type_label ? row.type_label : '-';
            var categoryClass = 'bg-secondary';
            if (category === 'Transfer') {
                categoryClass = 'bg-info';
            } else if (category === 'Refund') {
                categoryClass = 'bg-warning text-dark';
            } else if (category === 'Manual credit') {
                categoryClass = 'bg-primary';
            } else if (category === 'Top-up') {
                categoryClass = 'bg-success';
            } else if (category === 'Purchase') {
                categoryClass = 'bg-danger';
            } else if (category === 'Credit') {
                categoryClass = 'bg-success';
            }
            var typeClass = (row && row.type === 'credit') ? 'bg-success' : 'bg-danger';
            return '<div class="wallet-transaction-badges">' +
                '<span class="badge ' + categoryClass + '">' + walletEscapeHtml(category) + '</span>' +
                '<span class="badge ' + typeClass + '">' + walletEscapeHtml(type) + '</span>' +
                '</div>';
        }

        function walletPartyFormatter(value, row) {
            if (!row || !row.party_label) {
                return '<span class="text-muted">-</span>';
            }
            var direction = row.direction === 'incoming' ? 'From' : (row.direction === 'outgoing' ? 'To' : 'To');
            var label = direction + ' ' + row.party_label + (row.party_id ? (' #' + row.party_id) : '');
            return '<span class="fw-semibold" title="' + walletEscapeHtml(label) + '">' + walletEscapeHtml(label) + '</span>';
        }

        function walletAmountFormatter(value, row) {
            var amount = value !== null && value !== undefined ? Number(value).toFixed(2) : '0.00';
            var currency = row && row.currency ? row.currency : '';
            var css = row && row.type === 'credit' ? 'text-success' : 'text-danger';
            return '<span class="fw-semibold ' + css + '">' + amount + ' ' + walletEscapeHtml(currency) + '</span>';
        }

        function walletBalanceFormatter(value, row) {
            var balance = value !== null && value !== undefined ? Number(value).toFixed(2) : '0.00';
            var currency = row && row.currency ? row.currency : '';
            return '<span class="fw-semibold">' + balance + ' ' + walletEscapeHtml(currency) + '</span>';
        }

        function walletDateFormatter(value, row) {
            return walletEscapeHtml(row && row.created_human ? row.created_human : (value || '-'));
        }

        function walletOperateFormatter() {
            return '<button type="button" class="btn btn-sm btn-outline-primary wallet-preview-btn"><i class="bi bi-eye"></i></button>';
        }

        function setPreviewValue(key, value) {
            var el = document.querySelector('[data-preview="' + key + '"]');
            if (!el) {
                return;
            }
            el.textContent = value && String(value).trim() !== '' ? value : '-';
        }

        function openWalletPreview(row) {
            if (!row) {
                return;
            }
            setPreviewValue('reference', row.reference || (row.id ? ('#' + row.id) : '-'));
            setPreviewValue('operation', row.category_label || '-');
            setPreviewValue('party', row.party_label || '-');
            setPreviewValue('amount', (row.amount !== null && row.amount !== undefined ? Number(row.amount).toFixed(2) : '0.00') + ' ' + (row.currency || ''));
            setPreviewValue('balance', (row.balance_after !== null && row.balance_after !== undefined ? Number(row.balance_after).toFixed(2) : '0.00') + ' ' + (row.currency || ''));
            setPreviewValue('created_at', row.created_human || row.created_at || '-');
            setPreviewValue('operation_reference', row.operation_reference || '-');
            setPreviewValue('transfer_reference', row.transfer_reference || '-');
            setPreviewValue('transfer_key', row.transfer_key || '-');
            setPreviewValue('client_tag', row.client_tag || '-');
            setPreviewValue('reason', row.meta_reason || '-');
            setPreviewValue('notes', row.notes || '-');
            setPreviewValue('idempotency_key', row.idempotency_key || '-');
            setPreviewValue('manual_payment_request_id', row.manual_payment_request_id || '-');
            setPreviewValue('payment_transaction_id', row.payment_transaction_id || '-');

            var modalElement = document.getElementById('walletTransactionPreviewModal');
            if (modalElement) {
                var modal = bootstrap.Modal.getOrCreateInstance(modalElement);
                modal.show();
            }
        }

        window.walletTransactionEvents = {
            'click .wallet-preview-btn': function (e, value, row) {
                openWalletPreview(row);
            }
        };

        function walletTransactionsQueryParams(params) {
            var filter = document.getElementById('walletFilter');
            var search = document.getElementById('walletSearch');
            params.filter = filter ? filter.value : '';
            params.search = search ? search.value : '';
            return params;
        }

        document.addEventListener('DOMContentLoaded', function () {
            var $table = $('#wallet_transactions_table');
            var filter = document.getElementById('walletFilter');
            var searchInput = document.getElementById('walletSearch');
            var applyButton = document.getElementById('walletSearchApply');
            var resetButton = document.getElementById('walletSearchReset');

            function refreshTable(resetPage) {
                if (!$table.length) {
                    return;
                }
                var options = resetPage ? {pageNumber: 1} : {};
                $table.bootstrapTable('refresh', options);
            }

            if (filter) {
                filter.addEventListener('change', function () {
                    refreshTable(true);
                });
            }

            if (applyButton) {
                applyButton.addEventListener('click', function () {
                    refreshTable(true);
                });
            }

            if (resetButton) {
                resetButton.addEventListener('click', function () {
                    if (searchInput) {
                        searchInput.value = '';
                    }
                    if (filter) {
                        filter.value = 'all';
                    }
                    refreshTable(true);
                });
            }
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
                    <form method="get" class="filters-row" id="walletFilterForm" onsubmit="return false;">
                        <div>
                            <label for="walletFilter" class="form-label mb-1">{{ __('Movement type') }}</label>
                            <select id="walletFilter" name="filter" class="form-select">
                                @foreach($filters as $filterOption)
                                    <option value="{{ $filterOption }}" @selected($appliedFilter === $filterOption)>
                                        {{ __('wallet.filters.' . $filterOption) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="search-group">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="walletSearch" placeholder="{{ __('Search') }}" autocomplete="off">
                            </div>
                            <button class="btn btn-primary" type="button" id="walletSearchApply">{{ __('Search') }}</button>
                            <button class="btn btn-outline-secondary" type="button" id="walletSearchReset">{{ __('Reset') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card wallet-detail-table">
                <div class="card-header">
                    <div>
                        <h6 class="table-title">{{ __('Wallet transactions') }}</h6>
                        <p class="table-hint">{{ __('Review wallet activity with filters, exports, and previews.') }}</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table
                               class="table table-striped table-hover align-middle"
                               id="wallet_transactions_table"
                               data-toggle="table"
                               data-url="{{ route('wallet.transactions.datatable', $user) }}"
                               data-side-pagination="server"
                               data-pagination="true"
                               data-page-list="[10, 20, 50, 100, 200]"
                               data-search="false"
                               data-show-columns="true"
                               data-show-refresh="true"
                               data-show-export="true"
                               data-escape="false"
                               data-sort-name="id"
                               data-sort-order="desc"
                               data-icons="walletTransactionsTableIcons"
                               data-query-params="walletTransactionsQueryParams"
                               data-export-options='{"fileName": "wallet-transactions-{{ $user->getKey() }}","ignoreColumn": ["operate"]}'
                               data-export-types='["pdf","json","xml","csv","txt","sql","excel"]'
                               data-mobile-responsive="true">
                            <thead>
                            <tr>
                                <th data-field="reference" data-sortable="false" data-formatter="walletReferenceFormatter">{{ __('Reference') }}</th>
                                <th data-field="category_label" data-sortable="false" data-formatter="walletOperationFormatter">{{ __('Operation') }}</th>
                                <th data-field="party_label" data-sortable="false" data-formatter="walletPartyFormatter">{{ __('Transfer party') }}</th>
                                <th data-field="amount" data-sortable="true" data-align="end" data-formatter="walletAmountFormatter">{{ __('Amount') }}</th>
                                <th data-field="balance_after" data-sortable="true" data-align="end" data-formatter="walletBalanceFormatter">{{ __('Balance after') }}</th>
                                <th data-field="created_at" data-sortable="true" data-formatter="walletDateFormatter">{{ __('Created At') }}</th>
                                <th data-field="type_label" data-visible="false">{{ __('Type') }}</th>
                                <th data-field="direction" data-visible="false">{{ __('Direction') }}</th>
                                <th data-field="operation_reference" data-visible="false">{{ __('Operation reference') }}</th>
                                <th data-field="transfer_reference" data-visible="false">{{ __('Transfer reference') }}</th>
                                <th data-field="transfer_key" data-visible="false">{{ __('Transfer key') }}</th>
                                <th data-field="client_tag" data-visible="false">{{ __('Client tag') }}</th>
                                <th data-field="meta_reason" data-visible="false">{{ __('Reason') }}</th>
                                <th data-field="notes" data-visible="false">{{ __('Notes') }}</th>
                                <th data-field="idempotency_key" data-visible="false">{{ __('Idempotency key') }}</th>
                                <th data-field="manual_payment_request_id" data-visible="false">{{ __('Manual payment request') }}</th>
                                <th data-field="payment_transaction_id" data-visible="false">{{ __('Payment transaction') }}</th>
                                <th data-field="operate" data-align="center" data-formatter="walletOperateFormatter" data-events="walletTransactionEvents" data-escape="false">{{ __('Preview') }}</th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="walletTransactionPreviewModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Transaction preview') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="wallet-preview-grid">
                                <div class="wallet-preview-item">
                                    <div class="wallet-preview-label">{{ __('Reference') }}</div>
                                    <div class="wallet-preview-value" data-preview="reference">-</div>
                                </div>
                                <div class="wallet-preview-item">
                                    <div class="wallet-preview-label">{{ __('Operation') }}</div>
                                    <div class="wallet-preview-value" data-preview="operation">-</div>
                                </div>
                                <div class="wallet-preview-item">
                                    <div class="wallet-preview-label">{{ __('Transfer party') }}</div>
                                    <div class="wallet-preview-value" data-preview="party">-</div>
                                </div>
                                <div class="wallet-preview-item">
                                    <div class="wallet-preview-label">{{ __('Amount') }}</div>
                                    <div class="wallet-preview-value" data-preview="amount">-</div>
                                </div>
                                <div class="wallet-preview-item">
                                    <div class="wallet-preview-label">{{ __('Balance after') }}</div>
                                    <div class="wallet-preview-value" data-preview="balance">-</div>
                                </div>
                                <div class="wallet-preview-item">
                                    <div class="wallet-preview-label">{{ __('Created At') }}</div>
                                    <div class="wallet-preview-value" data-preview="created_at">-</div>
                                </div>
                                <div class="wallet-preview-item">
                                    <div class="wallet-preview-label">{{ __('Operation reference') }}</div>
                                    <div class="wallet-preview-value" data-preview="operation_reference">-</div>
                                </div>
                                <div class="wallet-preview-item">
                                    <div class="wallet-preview-label">{{ __('Transfer reference') }}</div>
                                    <div class="wallet-preview-value" data-preview="transfer_reference">-</div>
                                </div>
                                <div class="wallet-preview-item">
                                    <div class="wallet-preview-label">{{ __('Transfer key') }}</div>
                                    <div class="wallet-preview-value" data-preview="transfer_key">-</div>
                                </div>
                                <div class="wallet-preview-item">
                                    <div class="wallet-preview-label">{{ __('Client tag') }}</div>
                                    <div class="wallet-preview-value" data-preview="client_tag">-</div>
                                </div>
                                <div class="wallet-preview-item">
                                    <div class="wallet-preview-label">{{ __('Reason') }}</div>
                                    <div class="wallet-preview-value" data-preview="reason">-</div>
                                </div>
                                <div class="wallet-preview-item">
                                    <div class="wallet-preview-label">{{ __('Notes') }}</div>
                                    <div class="wallet-preview-value" data-preview="notes">-</div>
                                </div>
                                <div class="wallet-preview-item">
                                    <div class="wallet-preview-label">{{ __('Idempotency key') }}</div>
                                    <div class="wallet-preview-value" data-preview="idempotency_key">-</div>
                                </div>
                                <div class="wallet-preview-item">
                                    <div class="wallet-preview-label">{{ __('Manual payment request') }}</div>
                                    <div class="wallet-preview-value" data-preview="manual_payment_request_id">-</div>
                                </div>
                                <div class="wallet-preview-item">
                                    <div class="wallet-preview-label">{{ __('Payment transaction') }}</div>
                                    <div class="wallet-preview-value" data-preview="payment_transaction_id">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
