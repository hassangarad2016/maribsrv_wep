@extends('layouts.main')
@section('title')
    {{ __('لوحة القيادة') }}
@endsection

@section('content')
    <section class="section">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <div>
                <div class="text-muted small">{{ now()->translatedFormat('l، d F Y') }}</div>
                <h2 class="fw-bold mb-1">{{ __('مرحباً، هذا ملخص حي للنظام') }}</h2>
                <p class="text-muted mb-0">{{ __('تابع كل ما يحدث في الإعلانات، المدفوعات، البلاغات، والمستخدمين مباشرةً.') }}</p>
            </div>
        </div>

        @php
            $metric = function ($key) use ($counts) {
                return $counts[$key] ?? '—';
            };
        @endphp

        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small">{{ __('إجمالي المستخدمين') }}</span>
                            <i class="fa fa-users text-primary"></i>
                        </div>
                        <div class="fs-3 fw-bold">{{ $metric('users_total') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small">{{ __('الإعلانات المعتمدة') }}</span>
                            <i class="fa fa-check-circle text-success"></i>
                        </div>
                        <div class="fs-3 fw-bold">{{ $metric('items_approved') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small">{{ __('إعلانات بانتظار المراجعة') }}</span>
                            <i class="fa fa-hourglass-half text-warning"></i>
                        </div>
                        <div class="fs-3 fw-bold">{{ $metric('items_pending') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small">{{ __('طلبات دفع قيد المعالجة') }}</span>
                            <i class="fa fa-credit-card text-info"></i>
                        </div>
                        <div class="fs-3 fw-bold">{{ $metric('payments_pending') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small">{{ __('فشل/انتهت صلاحية المدفوعات') }}</span>
                            <i class="fa fa-times-circle text-danger"></i>
                        </div>
                        <div class="fs-3 fw-bold">{{ $metric('payments_failed') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small">{{ __('الطلبات اليدوية المفتوحة') }}</span>
                            <i class="fa fa-university text-secondary"></i>
                        </div>
                        <div class="fs-3 fw-bold">{{ $metric('manual_requests') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small">{{ __('بلاغات غير مقروءة') }}</span>
                            <i class="fa fa-flag text-danger"></i>
                        </div>
                        <div class="fs-3 fw-bold">{{ $metric('unread_reports') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small">{{ __('إشعارات غير مقروءة') }}</span>
                            <i class="fa fa-bell text-warning"></i>
                        </div>
                        <div class="fs-3 fw-bold">{{ $metric('notifications_unread') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">{{ __('التدفق الحي للأحداث') }}</h5>
                        <span class="text-muted small">{{ __('آخر الأنشطة في الإعلانات والمدفوعات') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            @forelse($recentItems as $item)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold">{{ $item->name }}</div>
                                        <div class="text-muted small">{{ __('إعلان') }} • {{ $item->status ?? __('غير محدد') }}</div>
                                    </div>
                                    <span class="text-muted small">{{ optional($item->created_at)->diffForHumans() }}</span>
                                </div>
                            @empty
                                <div class="text-center text-muted py-3">{{ __('لا توجد إعلانات حديثة') }}</div>
                            @endforelse

                            @foreach($recentPayments as $payment)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold">{{ __('دفعة #:id',['id'=>$payment->id]) }}</div>
                                        <div class="text-muted small">
                                            {{ $payment->payment_gateway ?? __('غير محدد') }} • {{ $payment->payment_status ?? __('غير محدد') }}
                                        </div>
                                    </div>
                                    <span class="text-muted small">
                                        @if(!empty($payment->amount))
                                            {{ $payment->amount }} {{ $payment->currency }}
                                        @endif
                                        • {{ optional($payment->created_at)->diffForHumans() }}
                                    </span>
                                </div>
                            @endforeach

                            @foreach($recentManualRequests as $manual)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold">{{ __('طلب يدوي #:id',['id'=>$manual->id]) }}</div>
                                        <div class="text-muted small">{{ $manual->status ?? __('غير محدد') }}</div>
                                    </div>
                                    <span class="text-muted small">
                                        @if(!empty($manual->amount))
                                            {{ $manual->amount }} {{ $manual->currency }}
                                        @endif
                                        • {{ optional($manual->created_at)->diffForHumans() }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">{{ __('آخر المستخدمين المسجلين') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            @forelse($recentUsers as $user)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold">{{ $user->name ?? __('بدون اسم') }}</div>
                                        <div class="text-muted small">{{ $user->email }}</div>
                                    </div>
                                    <span class="text-muted small">{{ optional($user->created_at)->diffForHumans() }}</span>
                                </div>
                            @empty
                                <div class="text-center text-muted py-3">{{ __('لا يوجد مستخدمون جدد') }}</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
