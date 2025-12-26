@extends('layouts.main')

@section('title')
    {{ __('Wallet Accounts') }}
@endsection

@section('css')
<style>
    .wallet-accounts-page {
        background: linear-gradient(180deg, rgba(13, 110, 253, 0.07), rgba(13, 110, 253, 0.02));
        border: 1px solid rgba(15, 23, 42, 0.06);
        border-radius: 1.25rem;
        color: #212529;
        padding: 1.25rem;
    }
    .wallet-accounts-shell {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .wallet-accounts-hero {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .wallet-accounts-title {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 700;
        color: #0f172a;
    }
    .wallet-accounts-subtitle {
        margin: 0.35rem 0 0;
        font-size: 0.9rem;
        color: #6c757d;
    }
    .wallet-accounts-metrics {
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
    .metric-card.accounts .metric-icon {
        color: #198754;
        background: rgba(25, 135, 84, 0.12);
    }
    .metric-card.activity .metric-icon {
        color: #0dcaf0;
        background: rgba(13, 202, 240, 0.18);
    }
    .metric-card.recent .metric-icon {
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
    .metric-sub {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .wallet-accounts-filters {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 1rem;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
    }
    .wallet-accounts-filters .card-body {
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
        align-items: center;
        gap: 0.75rem;
    }
    .search-group {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        margin-inline-start: auto;
    }
    .search-group .input-group {
        min-width: 240px;
        max-width: 360px;
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
    .wallet-accounts-table {
        border-radius: 1rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        margin-bottom: 0;
        overflow: hidden;
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
    }
    .wallet-accounts-table .card-header {
        background: #f8f9fb;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        padding: 0.9rem 1.1rem;
    }
    .wallet-accounts-table .card-body {
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
    .wallet-accounts-table .table thead th {
        background: #f8f9fa;
        color: #212529;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        padding: 0.85rem 1rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .wallet-accounts-table .table tbody td {
        padding: 0.85rem 1rem;
    }
    .wallet-accounts-table .table tbody tr:hover {
        background-color: rgba(13, 110, 253, 0.04);
    }
    .wallet-accounts-table .table-striped > tbody > tr:nth-of-type(odd) {
        background-color: rgba(15, 23, 42, 0.02);
    }

    @media (max-width: 768px) {
        .wallet-accounts-page {
            padding: 1rem;
        }
        .search-group {
            width: 100%;
            margin-inline-start: 0;
        }
        .search-group .input-group {
            flex: 1;
            min-width: 0;
            max-width: none;
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
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Wallet Accounts') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section wallet-accounts-page">
        <div class="wallet-accounts-shell">
            <div class="wallet-accounts-hero">
                <div>
                    <h5 class="wallet-accounts-title">@yield('title')</h5>
                    <p class="wallet-accounts-subtitle">{{ __('Track balances and manage user wallets from one place.') }}</p>
                </div>
                <div class="wallet-accounts-metrics">
                    <div class="metric-card">
                        <span class="metric-icon"><i class="bi bi-wallet2"></i></span>
                        <div>
                            <div class="metric-label">{{ __('Total Balance') }}</div>
                            <div class="metric-value">{{ number_format($totalBalance, 2) }} {{ $currency }}</div>
                        </div>
                    </div>
                    <div class="metric-card accounts">
                        <span class="metric-icon"><i class="bi bi-people"></i></span>
                        <div>
                            <div class="metric-label">{{ __('Total Accounts') }}</div>
                            <div class="metric-value">{{ number_format($accountCount) }}</div>
                        </div>
                    </div>
                    <div class="metric-card activity">
                        <span class="metric-icon"><i class="bi bi-clock-history"></i></span>
                        <div>
                            <div class="metric-label">{{ __('Latest Wallet Activity') }}</div>
                            <div class="metric-value">{{ $lastUpdatedAt ? $lastUpdatedAt->diffForHumans() : __('No activity yet') }}</div>
                        </div>
                    </div>
                    <div class="metric-card recent">
                        <span class="metric-icon"><i class="bi bi-arrow-repeat"></i></span>
                        <div>
                            <div class="metric-label">{{ __('Accounts refreshed today') }}</div>
                            <div class="metric-value">{{ number_format($recentlyUpdatedCount) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card wallet-accounts-filters">
                <div class="card-body">
                    <div class="filters-header">
                        <div>
                            <h6 class="filters-title">{{ __('Wallet Directory') }}</h6>
                            <p class="filters-hint">{{ __('Search and manage user wallet accounts.') }}</p>
                        </div>
                    </div>
                    <form method="get" class="filters-row" role="search">
                        <div class="search-group">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="search"
                                       name="search"
                                       value="{{ $search }}"
                                       class="form-control"
                                       placeholder="{{ __('Search by user name, email or mobile') }}">
                            </div>
                            <button type="submit" class="btn btn-primary d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-search"></i>
                                <span>{{ __('Search') }}</span>
                            </button>
                            <a href="{{ route('wallet.index') }}" class="btn btn-outline-secondary d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-arrow-repeat"></i>
                                <span>{{ __('Reset') }}</span>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card wallet-accounts-table">
                <div class="card-header">
                    <div>
                        <h6 class="table-title">{{ __('Wallet Directory') }}</h6>
                        <p class="table-hint">{{ __('Search and manage user wallet accounts.') }}</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th class="py-3">{{ __('User') }}</th>
                                <th class="text-center py-3">{{ __('Balance') }}</th>
                                <th class="py-3">{{ __('Updated At') }}</th>
                                <th class="text-center text-md-end py-3">{{ __('Actions') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($accounts as $account)
                                <tr class="table-row-spacious">
                                    <td class="py-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary" style="width: 48px; height: 48px;">
                                                <i class="bi bi-person-vcard fs-4"></i>
                                            </span>
                                            <div>
                                                <div class="fw-semibold">{{ $account->user?->name ?? __('Unknown User') }}</div>
                                                <div class="text-muted small">{{ $account->user?->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center py-4">
                                        <span class="badge bg-warning-subtle text-dark fw-semibold px-3 py-2">
                                            <i class="bi bi-coin"></i>
                                            {{ number_format((float) $account->balance, 2) }} {{ $currency }}
                                        </span>
                                    </td>
                                    <td class="py-4">
                                        <div class="small text-muted">
                                            <i class="bi bi-calendar-event me-1"></i>
                                            {{ optional($account->updated_at)->format('Y-m-d H:i') ?? __('Not updated') }}
                                        </div>
                                    </td>
                                    <td class="text-center text-md-end py-4">
                                        @if($account->user)
                                            <div class="btn-toolbar justify-content-center justify-content-md-end gap-2 flex-wrap" role="toolbar" aria-label="{{ __('Actions') }}">
                                                <button type="button"
                                                        class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2"
                                                        data-bs-toggle="offcanvas"
                                                        data-bs-target="#walletAccountPreview{{ $account->getKey() }}"
                                                        aria-controls="walletAccountPreview{{ $account->getKey() }}">
                                                    <i class="bi bi-eye"></i>
                                                    <span>{{ __('Preview') }}</span>
                                                </button>
                                                <a href="{{ route('wallet.show', $account->user) }}" class="btn btn-primary btn-sm d-flex align-items-center gap-2">
                                                    <i class="bi bi-wallet2"></i>
                                                    <span>{{ __('Open Wallet') }}</span>
                                                </a>
                                            </div>
                                        @else
                                            <span class="text-muted">{{ __('No user record') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($account->user)
                                    @push('modals')
                                        <div class="offcanvas offcanvas-end" tabindex="-1" id="walletAccountPreview{{ $account->getKey() }}" aria-labelledby="walletAccountPreviewLabel{{ $account->getKey() }}">
                                            <div class="offcanvas-header">
                                                <h5 class="offcanvas-title" id="walletAccountPreviewLabel{{ $account->getKey() }}">{{ __('Wallet Snapshot') }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('Close') }}"></button>
                                            </div>
                                            <div class="offcanvas-body">
                                                <div class="mb-3">
                                                    <p class="text-muted small mb-1">{{ __('Account Holder') }}</p>
                                                    <p class="fw-semibold mb-0">{{ $account->user?->name }}</p>
                                                    <p class="text-muted mb-0">{{ $account->user?->email }}</p>
                                                </div>
                                                <div class="mb-3">
                                                    <p class="text-muted small mb-1">{{ __('Current Balance') }}</p>
                                                    <p class="fw-bold fs-4 mb-0">
                                                        {{ number_format((float) $account->balance, 2) }} {{ $currency }}
                                                    </p>
                                                </div>
                                                <div class="mb-3">
                                                    <p class="text-muted small mb-1">{{ __('Last Updated') }}</p>
                                                    <p class="mb-0">{{ optional($account->updated_at)->format('Y-m-d H:i') ?? __('Not updated') }}</p>
                                                </div>
                                                <div class="d-grid gap-2">
                                                    <a href="{{ route('wallet.show', $account->user) }}" class="btn btn-primary">
                                                        <i class="bi bi-box-arrow-up-right"></i>
                                                        <span>{{ __('Open Wallet') }}</span>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    @endpush
                                @endif

                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <i class="bi bi-wallet2 display-6 d-block mb-2"></i>
                                        {{ __('No wallet accounts found for the current filters.') }}
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($accounts->hasPages())
                    <div class="card-footer bg-white border-0">
                        {{ $accounts->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
