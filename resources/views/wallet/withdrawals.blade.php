@extends('layouts.main')

@php
    use Illuminate\Support\Str;
@endphp

@section('title')
    {{ __('Wallet Withdrawal Requests') }}
@endsection

@section('css')
<style>
    .wallet-withdrawals-page {
        background: linear-gradient(180deg, rgba(13, 110, 253, 0.07), rgba(13, 110, 253, 0.02));
        border: 1px solid rgba(15, 23, 42, 0.06);
        border-radius: 1.25rem;
        color: #212529;
        padding: 1.25rem;
    }
    .wallet-withdrawals-shell {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .wallet-withdrawals-hero {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .wallet-withdrawals-title {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 700;
        color: #0f172a;
    }
    .wallet-withdrawals-subtitle {
        margin: 0.35rem 0 0;
        font-size: 0.9rem;
        color: #6c757d;
    }
    .wallet-withdrawals-metrics {
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
    .metric-card.approved .metric-icon {
        color: #198754;
        background: rgba(25, 135, 84, 0.12);
    }
    .metric-card.rejected .metric-icon {
        color: #dc3545;
        background: rgba(220, 53, 69, 0.12);
    }
    .metric-card.pending .metric-icon {
        color: #b58100;
        background: rgba(255, 193, 7, 0.18);
    }
    .metric-card.total .metric-icon {
        color: #0dcaf0;
        background: rgba(13, 202, 240, 0.18);
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
    .metric-sub {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .wallet-withdrawals-filters {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 1rem;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
    }
    .wallet-withdrawals-filters .card-body {
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
        min-width: 180px;
        border-radius: 0.75rem;
        border-color: rgba(15, 23, 42, 0.12);
    }
    .filters-row .btn {
        border-radius: 0.75rem;
    }
    .status-tabs {
        display: flex;
        flex-wrap: nowrap;
        gap: 0.5rem;
        overflow-x: auto;
        padding-bottom: 0.25rem;
        -webkit-overflow-scrolling: touch;
    }
    .status-tab {
        flex: 0 0 auto;
        border: 1px solid rgba(15, 23, 42, 0.15);
        background: #ffffff;
        color: #495057;
        border-radius: 0.75rem;
        padding: 0.4rem 0.9rem;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .status-tab:hover {
        border-color: #0d6efd;
        color: #0d6efd;
    }
    .status-tab.active {
        background: #0d6efd;
        color: #ffffff;
        border-color: #0d6efd;
        box-shadow: 0 10px 18px rgba(13, 110, 253, 0.2);
    }
    .status-tab.active:hover {
        color: #ffffff;
    }
    .withdrawals-quick-actions {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 0.9rem;
        background: #f8f9fb;
        padding: 0.85rem 1rem;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .withdrawals-quick-actions .badge {
        border-radius: 0.75rem;
    }
    .wallet-withdrawals-table {
        border-radius: 1rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        margin-bottom: 0;
        overflow: hidden;
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
    }
    .wallet-withdrawals-table .card-header {
        background: #f8f9fb;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        padding: 0.9rem 1.1rem;
    }
    .wallet-withdrawals-table .card-body {
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
    .wallet-withdrawals-table .table thead th {
        background: #f8f9fa;
        color: #212529;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        padding: 0.85rem 1rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .wallet-withdrawals-table .table {
        table-layout: fixed;
    }
    .wallet-withdrawals-table .table th,
    .wallet-withdrawals-table .table td {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .wallet-withdrawals-table .table th[data-field="operate"],
    .wallet-withdrawals-table .table td:last-child {
        min-width: 220px;
    }
    .withdrawal-actions {
        display: inline-flex;
        flex-wrap: nowrap;
        gap: 0.35rem;
        align-items: center;
    }
    .wallet-withdrawals-table .table tbody td {
        padding: 0.85rem 1rem;
    }
    .wallet-withdrawals-table .table tbody tr:hover {
        background-color: rgba(13, 110, 253, 0.04);
    }
    .wallet-withdrawals-table .table-striped > tbody > tr:nth-of-type(odd) {
        background-color: rgba(15, 23, 42, 0.02);
    }
    .wallet-withdrawals-table .fixed-table-toolbar {
        margin-bottom: 0.75rem;
    }
    .wallet-withdrawals-table .fixed-table-toolbar .columns {
        display: inline-flex;
        align-items: center;
        gap: 0.15rem;
        background: #4b5563;
        border-radius: 0.75rem;
        padding: 0.25rem;
        box-shadow: 0 10px 18px rgba(15, 23, 42, 0.18);
    }
    .wallet-withdrawals-table .fixed-table-toolbar .columns .btn,
    .wallet-withdrawals-table .fixed-table-toolbar .columns .btn-group > .btn {
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
    .wallet-withdrawals-table .fixed-table-toolbar .columns .btn:hover,
    .wallet-withdrawals-table .fixed-table-toolbar .columns .btn-group > .btn:hover {
        background: rgba(255, 255, 255, 0.12);
    }
    .wallet-withdrawals-table .fixed-table-toolbar .columns .dropdown-toggle::after {
        display: none;
    }
    .wallet-withdrawals-table .fixed-table-toolbar .columns .btn i {
        font-size: 1rem;
    }
    #wallet_withdrawals_table { width: 100%; }

    @media (min-width: 992px) {
        .wallet-withdrawals-hero {
            flex-direction: column;
            align-items: stretch;
        }
    }

    @media (max-width: 768px) {
        .wallet-withdrawals-page {
            padding: 1rem;
        }
        .filters-row .form-select {
            min-width: 0;
            width: 100%;
        }
    }
</style>
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row align-items-center g-2">
            <div class="col-12 col-md-6 order-md-1 order-last text-center text-md-start">
                <h4 class="mb-0">@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first text-center text-md-end">
                <nav aria-label="breadcrumb" class="breadcrumb-header">
                    <ol class="breadcrumb mb-0 justify-content-center justify-content-md-end">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('wallet.index') }}">{{ __('Wallet Accounts') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Withdrawal Requests') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section wallet-withdrawals-page">
        @php
            $statusIcons = [
                \App\Models\WalletWithdrawalRequest::STATUS_PENDING => 'bi bi-hourglass-split',
                \App\Models\WalletWithdrawalRequest::STATUS_APPROVED => 'bi bi-check-circle',
                \App\Models\WalletWithdrawalRequest::STATUS_REJECTED => 'bi bi-x-circle',
            ];
            $statusMetricClass = [
                \App\Models\WalletWithdrawalRequest::STATUS_PENDING => 'pending',
                \App\Models\WalletWithdrawalRequest::STATUS_APPROVED => 'approved',
                \App\Models\WalletWithdrawalRequest::STATUS_REJECTED => 'rejected',
            ];
        @endphp
        <div class="wallet-withdrawals-shell">
            <div class="wallet-withdrawals-hero">
                <div>
                    <h5 class="wallet-withdrawals-title">@yield('title')</h5>
                    <p class="wallet-withdrawals-subtitle">{{ __('Review and manage wallet withdrawal requests from users.') }}</p>
                </div>
                <div class="wallet-withdrawals-metrics">
                    @foreach($statusSummaries as $summary)
                        @php
                            $statusKey = $summary['status'];
                            $icon = $statusIcons[$statusKey] ?? 'bi bi-cash-stack';
                            $metricClass = $statusMetricClass[$statusKey] ?? 'pending';
                        @endphp
                        <div class="metric-card {{ $metricClass }}">
                            <span class="metric-icon"><i class="{{ $icon }}"></i></span>
                            <div>
                                <div class="metric-label">{{ $summary['label'] }}</div>
                                <div class="metric-value">{{ number_format($summary['count']) }}</div>
                                <div class="metric-sub">{{ number_format($summary['amount'], 2) }} {{ $currency }}</div>
                            </div>
                        </div>
                    @endforeach
                    <div class="metric-card total">
                        <span class="metric-icon"><i class="bi bi-graph-up"></i></span>
                        <div>
                            <div class="metric-label">{{ __('Total Volume') }}</div>
                            <div class="metric-value">{{ number_format($totalWithdrawalAmount, 2) }} {{ $currency }}</div>
                            <div class="metric-sub">{{ __('Total withdrawal amount processed') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card wallet-withdrawals-filters">
                <div class="card-body">
                    <div class="filters-header">
                        <div>
                            <h6 class="filters-title">{{ __('Withdrawal Overview') }}</h6>
                            <p class="filters-hint">{{ __('Approve or reject pending withdrawals directly from this toolbar.') }}</p>
                        </div>
                    </div>
                    <form id="withdrawalsFilterForm" class="filters-row" onsubmit="return false;">
                        <div class="status-tabs" role="tablist">
                            <button type="button" class="status-tab {{ empty($filters['status']) ? 'active' : '' }}" data-status="">{{ __('All statuses') }}</button>
                            <button type="button" class="status-tab {{ $filters['status'] === \App\Models\WalletWithdrawalRequest::STATUS_PENDING ? 'active' : '' }}" data-status="{{ \App\Models\WalletWithdrawalRequest::STATUS_PENDING }}">{{ $statusOptions[\App\Models\WalletWithdrawalRequest::STATUS_PENDING] ?? __('Pending') }}</button>
                            <button type="button" class="status-tab {{ $filters['status'] === \App\Models\WalletWithdrawalRequest::STATUS_APPROVED ? 'active' : '' }}" data-status="{{ \App\Models\WalletWithdrawalRequest::STATUS_APPROVED }}">{{ $statusOptions[\App\Models\WalletWithdrawalRequest::STATUS_APPROVED] ?? __('Approved') }}</button>
                            <button type="button" class="status-tab {{ $filters['status'] === \App\Models\WalletWithdrawalRequest::STATUS_REJECTED ? 'active' : '' }}" data-status="{{ \App\Models\WalletWithdrawalRequest::STATUS_REJECTED }}">{{ $statusOptions[\App\Models\WalletWithdrawalRequest::STATUS_REJECTED] ?? __('Rejected') }}</button>
                        </div>
                        <input type="hidden" name="status" id="status_filter" value="{{ $filters['status'] }}">
                        <div>
                            <label for="method" class="form-label mb-1">{{ __('Withdrawal Method') }}</label>
                            <select id="method" name="method" class="form-select">
                                <option value="">{{ __('All methods') }}</option>
                                @foreach($methodOptions as $value => $option)
                                    <option value="{{ $value }}" @selected($filters['method'] === $value)>{{ $option['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <a href="{{ route('wallet.withdrawals.index') }}" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-arrow-repeat me-1"></i>{{ __('Reset') }}
                            </a>
                        </div>
                    </form>
                    <div class="withdrawals-quick-actions">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary text-white">
                                <i class="bi bi-lightning-charge"></i>
                            </span>
                            <div>
                                <p class="fw-semibold mb-0">{{ __('Quick Decision Bar') }}</p>
                                <p class="text-muted small mb-0">{{ __('Approve or reject pending withdrawals directly from this toolbar.') }}</p>
                            </div>
                        </div>
                        <div class="btn-group" role="group" aria-label="{{ __('Quick Decision Bar') }}">
                            <a href="{{ request()->fullUrlWithQuery(['status' => \App\Models\WalletWithdrawalRequest::STATUS_PENDING]) }}" class="btn btn-warning d-flex align-items-center gap-2">
                                <i class="bi bi-hourglass-split"></i>
                                <span>{{ __('View Pending') }}</span>
                            </a>
                            <a href="{{ request()->fullUrlWithQuery(['status' => \App\Models\WalletWithdrawalRequest::STATUS_APPROVED]) }}" class="btn btn-success d-flex align-items-center gap-2">
                                <i class="bi bi-check-circle"></i>
                                <span>{{ __('View Approved') }}</span>
                            </a>
                            <a href="{{ request()->fullUrlWithQuery(['status' => \App\Models\WalletWithdrawalRequest::STATUS_REJECTED]) }}" class="btn btn-danger d-flex align-items-center gap-2">
                                <i class="bi bi-x-circle"></i>
                                <span>{{ __('View Rejected') }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card wallet-withdrawals-table">
                <div class="card-header">
                    <div>
                        <h6 class="table-title">{{ __('Withdrawal Requests') }}</h6>
                        <p class="table-hint">{{ __('Review and manage wallet withdrawal requests from users.') }}</p>
                    </div>
                </div>
                <div class="card-body">
                        <div class="table-responsive">
                            <table
                               class="table table-striped table-hover align-middle mb-0"
                               aria-describedby="walletWithdrawalsTableCaption"
                               id="wallet_withdrawals_table"
                               data-toggle="table"
                               data-url="{{ route('wallet.withdrawals.datatable') }}"
                               data-click-to-select="true"
                               data-side-pagination="server"
                               data-pagination="true"
                               data-page-list="[5, 10, 20, 50, 100, 200]"
                               data-search="true"
                               data-show-columns="true"
                               data-show-refresh="true"
                               data-trim-on-search="false"
                               data-escape="false"
                               data-responsive="true"
                               data-sort-name="id"
                               data-sort-order="desc"
                               data-pagination-successively-size="3"
                               data-show-export="true"
                               data-export-options='{"fileName": "wallet-withdrawals","ignoreColumn": ["operate"]}'
                               data-export-types='["pdf","json","xml","csv","txt","sql","doc","excel"]'
                               data-icons="walletWithdrawalsTableIcons"
                               data-icons-prefix="bi"
                               data-mobile-responsive="true"
                               data-query-params="queryParams">
                                <thead class="table-light">
                                <tr>
                                    <th data-field="id" data-sortable="true" data-formatter="withdrawalRequestFormatter">{{ __('Request') }}</th>
                                    <th data-field="user.name" data-sortable="false" data-formatter="userFormatter">{{ __('User') }}</th>
                                    <th data-field="amount" data-sortable="true" data-formatter="amountFormatter">{{ __('Amount') }}</th>
                                    <th data-field="preferred_method" data-sortable="false" data-formatter="methodFormatter">{{ __('Method') }}</th>
                                    <th data-field="notes" data-sortable="false" data-formatter="notesFormatter" data-visible="false">{{ __('Notes') }}</th>
                                    <th data-field="created_at" data-sortable="true" data-formatter="createdAtFormatter">{{ __('Requested At') }}</th>
                                    <th data-field="status" data-sortable="true" data-formatter="statusFormatter">{{ __('Status') }}</th>
                                    <th data-field="operate" data-align="center" data-sortable="false" data-events="withdrawalEvents">{{ __('Actions') }}</th>
                                </tr>
                                </thead>
                            </table>
                        </div>
                        <div id="walletWithdrawalsTableCaption" class="visually-hidden">{{ __('Review and manage wallet withdrawal requests from users.') }}</div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="withdrawalPreviewModal" tabindex="-1" aria-labelledby="withdrawalPreviewLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="withdrawalPreviewLabel">{{ __('Withdrawal Details') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4 text-muted">{{ __('Request ID') }}</dt>
                            <dd class="col-sm-8" id="withdrawalPreviewId">-</dd>

                            <dt class="col-sm-4 text-muted">{{ __('Status') }}</dt>
                            <dd class="col-sm-8">
                                <span id="withdrawalPreviewStatus" class="badge bg-secondary">-</span>
                            </dd>

                            <dt class="col-sm-4 text-muted">{{ __('Amount') }}</dt>
                            <dd class="col-sm-8" id="withdrawalPreviewAmount">-</dd>

                            <dt class="col-sm-4 text-muted">{{ __('Method') }}</dt>
                            <dd class="col-sm-8" id="withdrawalPreviewMethod">-</dd>

                            <dt class="col-sm-4 text-muted">{{ __('رقم الحساب') }}</dt>
                            <dd class="col-sm-8" id="withdrawalPreviewAccountNumber">-</dd>

                            <dt class="col-sm-4 text-muted">{{ __('رقم الهاتف') }}</dt>
                            <dd class="col-sm-8" id="withdrawalPreviewPhoneNumber">-</dd>

                            <dt class="col-sm-4 text-muted">{{ __('User') }}</dt>
                            <dd class="col-sm-8">
                                <div id="withdrawalPreviewUser">-</div>
                                <div id="withdrawalPreviewEmail" class="text-muted small"></div>
                            </dd>

                            <dt class="col-sm-4 text-muted">{{ __('Reference') }}</dt>
                            <dd class="col-sm-8" id="withdrawalPreviewReference">-</dd>

                            <dt class="col-sm-4 text-muted">{{ __('Notes') }}</dt>
                            <dd class="col-sm-8" id="withdrawalPreviewNotes">-</dd>

                            <dt class="col-sm-4 text-muted">{{ __('Review Notes') }}</dt>
                            <dd class="col-sm-8" id="withdrawalPreviewReviewNotes">-</dd>

                            <dt class="col-sm-4 text-muted">{{ __('Requested At') }}</dt>
                            <dd class="col-sm-8" id="withdrawalPreviewCreatedAt">-</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('script')
<script>
    window.walletWithdrawalsTableIcons = {
        refresh: 'bi-arrow-clockwise',
        columns: 'bi-list-ul',
        export: 'bi-download'
    };
    const WALLET_CURRENCY = @json($currency);

    function escapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value).replace(/[&<>"'`=\/]/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
                '/': '&#x2F;',
                '`': '&#x60;',
                '=': '&#x3D;'
            }[char];
        });
    }

    function withdrawalRequestFormatter(value, row) {
        const label = value ? `#${escapeHtml(value)}` : '-';
        const reference = row.wallet_reference ? `Ref: ${escapeHtml(row.wallet_reference)}` : '';
        const title = reference ? ` title="${reference}"` : '';
        return `<span class="badge bg-primary text-white fw-semibold px-3 py-2"${title}>${label}</span>`;
    }

    function userFormatter(value, row) {
        const name = row.user && row.user.name ? row.user.name : '-';
        const email = row.user && row.user.email ? row.user.email : '';
        const title = email && email !== '-' ? ` title="${escapeHtml(email)}"` : '';
        return `<span class="fw-semibold"${title}>${escapeHtml(name)}</span>`;
    }

    function amountFormatter(value) {
        let amount = Number(value);
        if (Number.isNaN(amount)) {
            amount = 0;
        }
        const formatted = amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return `<span class="fw-semibold">${formatted}</span> <span class="text-muted small">${escapeHtml(WALLET_CURRENCY)}</span>`;
    }

    function methodFormatter(value, row) {
        const label = row.method_label || value || '-';
        return `<span class="badge bg-light text-dark">${escapeHtml(label)}</span>`;
    }

    function notesFormatter(value) {
        if (!value) {
            return '<span class="text-muted">-</span>';
        }
        const full = String(value);
        const short = full.length > 60 ? `${full.slice(0, 57)}...` : full;
        return `<span title="${escapeHtml(full)}">${escapeHtml(short)}</span>`;
    }

    function createdAtFormatter(value, row) {
        const label = row.created_human || value || '-';
        return `<span class="text-nowrap">${escapeHtml(label)}</span>`;
    }

    function statusFormatter(value, row) {
        const status = row.status || value || 'pending';
        const label = row.status_label || status;
        const badgeClass = {
            pending: 'warning',
            approved: 'success',
            rejected: 'danger'
        }[status] || 'secondary';
        return `<span class="badge bg-${badgeClass} text-white">${escapeHtml(label)}</span>`;
    }

    function queryParams(params) {
        return {
            status_filter: $('#status_filter').val(),
            method_filter: $('#method').val(),
            offset: params.offset,
            limit: params.limit,
            search: params.search,
            sort: params.sort,
            order: params.order,
            filter: params.filter
        };
    }

    function fillPreviewModal(row) {
        const statusClass = {
            pending: 'bg-warning',
            approved: 'bg-success',
            rejected: 'bg-danger'
        }[row.status] || 'bg-secondary';
        const meta = row.meta || {};
        const accountNumber = meta.account_number || '-';
        const phoneNumber = meta.contact_number || meta.phone || '-';

        $('#withdrawalPreviewId').text(row.id ?? '-');
        $('#withdrawalPreviewStatus')
            .attr('class', `badge ${statusClass}`)
            .text(row.status_label || row.status || '-');
        $('#withdrawalPreviewAmount').text(`${row.amount ?? 0} ${WALLET_CURRENCY}`);
        $('#withdrawalPreviewMethod').text(row.method_label || row.preferred_method || '-');
        $('#withdrawalPreviewAccountNumber').text(accountNumber);
        $('#withdrawalPreviewPhoneNumber').text(phoneNumber);
        $('#withdrawalPreviewUser').text(row.user && row.user.name ? row.user.name : '-');
        $('#withdrawalPreviewEmail').text(row.user && row.user.email ? row.user.email : '');
        $('#withdrawalPreviewReference').text(row.wallet_reference || '-');
        $('#withdrawalPreviewNotes').text(row.notes || '-');
        $('#withdrawalPreviewReviewNotes').text(row.review_notes || '-');
        $('#withdrawalPreviewCreatedAt').text(row.created_human || row.created_at || '-');
    }

    window.withdrawalEvents = {
        'click .preview-withdrawal': function (e, value, row) {
            fillPreviewModal(row);
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('withdrawalPreviewModal'));
            modal.show();
        }
    };

    $(document).ready(function () {
        const $table = $('#wallet_withdrawals_table');
        const $statusFilter = $('#status_filter');

        function refreshTableToFirstPage() {
            const options = $table.bootstrapTable('getOptions');
            options.pageNumber = 1;
            $table.bootstrapTable('refresh');
        }

        $('.status-tab').on('click', function () {
            $('.status-tab').removeClass('active');
            $(this).addClass('active');
            $statusFilter.val($(this).data('status'));
            refreshTableToFirstPage();
        });

        $('#method').on('change', function () {
            refreshTableToFirstPage();
        });
    });
</script>
@endsection
