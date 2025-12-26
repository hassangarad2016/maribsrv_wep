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
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 0.75rem;
    }
    .metric-card {
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
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .status-tab {
        border: 1px solid rgba(15, 23, 42, 0.15);
        background: #ffffff;
        color: #495057;
        border-radius: 0.75rem;
        padding: 0.4rem 0.9rem;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
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
    .wallet-withdrawals-table .table tbody td {
        padding: 0.85rem 1rem;
    }
    .wallet-withdrawals-table .table tbody tr:hover {
        background-color: rgba(13, 110, 253, 0.04);
    }
    .wallet-withdrawals-table .table-striped > tbody > tr:nth-of-type(odd) {
        background-color: rgba(15, 23, 42, 0.02);
    }

    @media (min-width: 992px) {
        .wallet-withdrawals-hero {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }
        .wallet-withdrawals-metrics {
            max-width: 720px;
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
                    <form method="get" id="withdrawalsFilterForm" class="filters-row">
                        <div class="status-tabs" role="tablist">
                            <button type="button" class="status-tab {{ $filters['status'] === '' ? 'active' : '' }}" data-status="">{{ __('All statuses') }}</button>
                            <button type="button" class="status-tab {{ $filters['status'] === \App\Models\WalletWithdrawalRequest::STATUS_PENDING ? 'active' : '' }}" data-status="{{ \App\Models\WalletWithdrawalRequest::STATUS_PENDING }}">{{ $statusOptions[\App\Models\WalletWithdrawalRequest::STATUS_PENDING] ?? __('Pending') }}</button>
                            <button type="button" class="status-tab {{ $filters['status'] === \App\Models\WalletWithdrawalRequest::STATUS_APPROVED ? 'active' : '' }}" data-status="{{ \App\Models\WalletWithdrawalRequest::STATUS_APPROVED }}">{{ $statusOptions[\App\Models\WalletWithdrawalRequest::STATUS_APPROVED] ?? __('Approved') }}</button>
                            <button type="button" class="status-tab {{ $filters['status'] === \App\Models\WalletWithdrawalRequest::STATUS_REJECTED ? 'active' : '' }}" data-status="{{ \App\Models\WalletWithdrawalRequest::STATUS_REJECTED }}">{{ $statusOptions[\App\Models\WalletWithdrawalRequest::STATUS_REJECTED] ?? __('Rejected') }}</button>
                        </div>
                        <input type="hidden" name="status" id="status_filter" value="{{ $filters['status'] }}">
                        <div>
                            <label for="method" class="form-label mb-1">{{ __('Withdrawal Method') }}</label>
                            <select id="method" name="method" class="form-select" onchange="this.form.submit()">
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
                            <table class="table table-striped table-hover align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th class="py-3">{{ __('Request') }}</th>
                                    <th class="py-3">{{ __('User') }}</th>
                                    <th class="text-center text-md-start py-3">{{ __('Amount') }}</th>
                                    <th class="py-3">{{ __('Method') }}</th>
                                    <th class="py-3">{{ __('Notes') }}</th>
                                    <th class="py-3">{{ __('Requested At') }}</th>
                                    <th class="text-center text-md-end py-3">{{ __('Actions') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($withdrawals as $withdrawal)
                                    @php
                                        $statusClass = match ($withdrawal->status) {
                                            \App\Models\WalletWithdrawalRequest::STATUS_APPROVED => 'bg-success-subtle text-success',
                                            \App\Models\WalletWithdrawalRequest::STATUS_REJECTED => 'bg-danger-subtle text-danger',
                                            default => 'bg-warning-subtle text-warning'
                                        };
                                        $methodLabel = $methodOptions[$withdrawal->preferred_method]['name'] ?? Str::headline(str_replace('_', ' ', $withdrawal->preferred_method));
                                    @endphp
                                    <tr class="table-row-spacious">
                                        <td class="py-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-secondary-subtle text-secondary" style="width: 48px; height: 48px;">
                                                    <i class="bi bi-receipt fs-4"></i>
                                                </span>
                                                <div>
                                                    <div class="fw-semibold">#{{ $withdrawal->getKey() }}</div>
                                                    <span class="badge {{ $statusClass }} mt-2">{{ $statusOptions[$withdrawal->status] ?? Str::headline($withdrawal->status) }}</span>
                                                </div>
                                            </div>

                                        </td>
                                        <td class="py-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary" style="width: 48px; height: 48px;">
                                                    <i class="bi bi-person-circle fs-4"></i>
                                                </span>
                                                <div>
                                                    <div class="fw-semibold">{{ $withdrawal->account?->user?->name ?? __('Unknown User') }}</div>
                                                    <div class="text-muted small">{{ $withdrawal->account?->user?->email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center text-md-start py-4">
                                            <span class="badge bg-warning-subtle text-warning fw-semibold px-3 py-2">
                                                <i class="bi bi-cash-stack"></i>
                                                {{ number_format((float) $withdrawal->amount, 2) }} {{ $currency }}
                                            </span>

                                        </td>
                                        <td class="py-4">
                                            <span class="badge bg-info-subtle text-info">{{ $methodLabel }}</span>
                                                @if($withdrawal->wallet_reference)
                                                    <div class="text-muted small mt-1 d-flex align-items-center gap-2">
                                                        <i class="bi bi-link-45deg"></i>
                                                        <span>{{ $withdrawal->wallet_reference }}</span>
                                                    </div>
                                                @endif
                                        </td>
                                        <td class="py-4">
                                            @if($withdrawal->notes)
                                                <div class="small">{{ $withdrawal->notes }}</div>
                                            @else
                                                <span class="text-muted small">{{ __('No notes provided.') }}</span>
                                            @endif
                                            @if($withdrawal->review_notes)
                                                <div class="small text-muted mt-1">{{ __('Reviewer notes:') }} {{ $withdrawal->review_notes }}</div>
                                            @endif
                                        </td>
                                        <td class="py-4">
                                            <div class="small d-flex align-items-center gap-2">
                                                <i class="bi bi-calendar-week"></i>
                                                <span>{{ optional($withdrawal->created_at)->format('Y-m-d H:i') }}</span>
                                            </div>
                                            <div class="text-muted small mt-1 d-flex align-items-center gap-2">
                                                <i class="bi bi-clock-history"></i>
                                                <span>{{ optional($withdrawal->created_at)->diffForHumans() }}</span>
                                            </div>

                                        </td>
                                        <td class="text-center text-md-end py-4">
                                            <div class="btn-toolbar justify-content-center justify-content-md-end gap-2 flex-wrap" role="toolbar" aria-label="{{ __('Actions') }}">
                                                <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2"
                                                        data-bs-toggle="offcanvas"
                                                        data-bs-target="#withdrawalPreview{{ $withdrawal->getKey() }}"
                                                        aria-controls="withdrawalPreview{{ $withdrawal->getKey() }}">
                                                    <i class="bi bi-eye"></i>
                                                    <span>{{ __('Preview') }}</span>
                                                </button>
                                                @if($withdrawal->isPending())
                                                    <form method="post" action="{{ route('wallet.withdrawals.approve', $withdrawal) }}" class="d-flex">


                                                        @csrf
                                                        <button type="submit" class="btn btn-success btn-sm d-flex align-items-center gap-2" onclick="return confirm('{{ __('Approve this withdrawal request?') }}');">
                                                            <i class="bi bi-check-circle"></i>
                                                            <span>{{ __('Approve') }}</span>

                                                        </button>
                                                    </form>
                                                    <form method="post" action="{{ route('wallet.withdrawals.reject', $withdrawal) }}" class="d-flex">
                                                        @csrf
                                                        <button type="submit" class="btn btn-danger btn-sm d-flex align-items-center gap-2" onclick="return confirm('{{ __('Reject this withdrawal request?') }}');">
                                                            <i class="bi bi-x-circle"></i>
                                                            <span>{{ __('Reject') }}</span>
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary">{{ __('Processed') }}</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @push('modals')
                                        <div class="offcanvas offcanvas-end" tabindex="-1" id="withdrawalPreview{{ $withdrawal->getKey() }}" aria-labelledby="withdrawalPreviewLabel{{ $withdrawal->getKey() }}">
                                            <div class="offcanvas-header">
                                                <h5 class="offcanvas-title" id="withdrawalPreviewLabel{{ $withdrawal->getKey() }}">{{ __('Withdrawal Snapshot') }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('Close') }}"></button>
                                            </div>
                                            <div class="offcanvas-body">
                                                <div class="mb-3">
                                                    <p class="text-muted small mb-1">{{ __('Request Identifier') }}</p>
                                                    <p class="fw-semibold mb-0">#{{ $withdrawal->getKey() }}</p>
                                                    <span class="badge {{ $statusClass }} mt-2">{{ $statusOptions[$withdrawal->status] ?? Str::headline($withdrawal->status) }}</span>
                                                </div>
                                                <div class="mb-3">
                                                    <p class="text-muted small mb-1">{{ __('Requested Amount') }}</p>
                                                    <p class="fw-bold fs-4 mb-0">{{ number_format((float) $withdrawal->amount, 2) }} {{ $currency }}</p>
                                                </div>
                                                <div class="mb-3">
                                                    <p class="text-muted small mb-1">{{ __('Preferred Method') }}</p>
                                                    <p class="mb-0">{{ $methodLabel }}</p>
                                                    @if($withdrawal->wallet_reference)
                                                        <a href="{{ $withdrawal->wallet_reference }}" target="_blank" rel="noopener" class="d-inline-flex align-items-center gap-1 mt-1">
                                                            <i class="bi bi-box-arrow-up-right"></i>
                                                            <span>{{ __('Open reference link') }}</span>
                                                        </a>
                                                    @endif
                                                </div>
                                                <div class="mb-3">
                                                    <p class="text-muted small mb-1">{{ __('Notes') }}</p>
                                                    <p class="mb-0">{{ $withdrawal->notes ?: __('No notes provided.') }}</p>
                                                </div>
                                                @if($withdrawal->review_notes)
                                                    <div class="mb-3">
                                                        <p class="text-muted small mb-1">{{ __('Review Notes') }}</p>
                                                        <p class="mb-0">{{ $withdrawal->review_notes }}</p>
                                                    </div>
                                                @endif
                                                @php
                                                    $documents = collect($withdrawal->meta['documents'] ?? [])->filter(fn ($doc) => filled($doc));
                                                @endphp
                                                <div class="mb-3">
                                                    <p class="text-muted small mb-2">{{ __('Supporting Documents') }}</p>
                                                    @if($documents->isNotEmpty())
                                                        <ul class="list-unstyled mb-0 d-grid gap-2">
                                                            @foreach($documents as $document)
                                                                @php
                                                                    $link = is_array($document) ? ($document['url'] ?? ($document['link'] ?? null)) : $document;
                                                                    $label = is_array($document) ? ($document['name'] ?? ($document['label'] ?? $link)) : $document;
                                                                @endphp
                                                                @if($link)
                                                                    <li>
                                                                        <a href="{{ $link }}" target="_blank" rel="noopener" class="d-inline-flex align-items-center gap-2">
                                                                            <i class="bi bi-paperclip"></i>
                                                                            <span>{{ $label }}</span>
                                                                        </a>
                                                                    </li>
                                                                @endif
                                                            @endforeach
                                                        </ul>
                                                    @else
                                                        <p class="text-muted small mb-0">{{ __('No documents provided.') }}</p>
                                                    @endif
                                                </div>
                                                <div class="d-grid gap-2">
                                                    @if($withdrawal->isPending())
                                                        <form method="post" action="{{ route('wallet.withdrawals.approve', $withdrawal) }}">
                                                            @csrf
                                                            <button type="submit" class="btn btn-success d-flex align-items-center gap-2" onclick="return confirm('{{ __('Approve this withdrawal request?') }}');">
                                                                <i class="bi bi-check-circle"></i>
                                                                <span>{{ __('Approve') }}</span>
                                                            </button>
                                                        </form>
                                                        <form method="post" action="{{ route('wallet.withdrawals.reject', $withdrawal) }}">
                                                            @csrf
                                                            <button type="submit" class="btn btn-danger d-flex align-items-center gap-2" onclick="return confirm('{{ __('Reject this withdrawal request?') }}');">
                                                                <i class="bi bi-x-circle"></i>
                                                                <span>{{ __('Reject') }}</span>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endpush    

                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="bi bi-cash-coin display-6 d-block mb-2"></i>
                                            {{ __('No withdrawal requests found for the selected filters.') }}
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                </div>
                @if($withdrawals->hasPages())
                    <div class="card-footer bg-white border-0">
                        {{ $withdrawals->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection

@section('script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('withdrawalsFilterForm');
        const statusInput = document.getElementById('status_filter');
        const tabs = document.querySelectorAll('.status-tab');

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                tabs.forEach((item) => item.classList.remove('active'));
                tab.classList.add('active');
                statusInput.value = tab.dataset.status || '';
                form.submit();
            });
        });
    });
</script>
@endsection
