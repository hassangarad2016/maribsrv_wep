@extends('layouts.main')
@section('title')
    {{ __('لوحة القيادة') }}
@endsection

@section('content')
    <section class="section">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <div>
                <div class="text-muted small">{{ now()->translatedFormat('l, d F Y') }}</div>
                <h2 class="fw-bold mb-1">{{ __('مركز المراقبة اللحظية') }}</h2>
                <p class="text-muted mb-0">{{ __('واجهة حية تتابع الإعلانات والمدفوعات والبلاغات والاتصالات فور حدوثها.') }}</p>
            </div>
            <span class="badge bg-success-subtle text-success fw-semibold px-3 py-2">{{ __('بث مباشر') }}</span>
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
                        <div class="fs-3 fw-bold" data-metric="users_total">{{ $metric('users_total') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small">{{ __('المتصلون الآن') }}</span>
                            <i class="fa fa-signal text-success"></i>
                        </div>
                        <div class="fs-3 fw-bold" data-metric="users_online">{{ $metric('users_online') }}</div>
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
                        <div class="fs-3 fw-bold" data-metric="items_approved">{{ $metric('items_approved') }}</div>
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
                        <div class="fs-3 fw-bold" data-metric="items_pending">{{ $metric('items_pending') }}</div>
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
                        <div class="fs-3 fw-bold" data-metric="payments_pending">{{ $metric('payments_pending') }}</div>
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
                        <div class="fs-3 fw-bold" data-metric="payments_failed">{{ $metric('payments_failed') }}</div>
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
                        <div class="fs-3 fw-bold" data-metric="manual_requests">{{ $metric('manual_requests') }}</div>
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
                        <div class="fs-3 fw-bold" data-metric="unread_reports">{{ $metric('unread_reports') }}</div>
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
                        <div class="fs-3 fw-bold" data-metric="notifications_unread">{{ $metric('notifications_unread') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="mb-0">{{ __('الرسم اللحظي للنشاط العام') }}</h5>
                            <small class="text-muted">{{ __('يتم التحديث كل 30 ثانية تلقائياً') }}</small>
                        </div>
                        <span class="badge bg-primary-subtle text-primary">{{ __('مباشر') }}</span>
                    </div>
                    <div class="card-body">
                        <div id="live-activity-chart" style="height: 320px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">{{ __('حالة الاتصال الآن') }}</h5>
                        <span class="badge bg-success">{{ __('متصل') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <div class="text-muted small">{{ __('المستخدمون المتصلون الآن') }}</div>
                                <div class="fs-2 fw-bold" data-metric="users_online">{{ $metric('users_online') }}</div>
                            </div>
                            <i class="fa fa-broadcast-tower text-success fs-3"></i>
                        </div>
                        <div class="bg-light rounded-3 p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="text-muted small">{{ __('إجمالي المستخدمين') }}</div>
                                    <div class="fw-semibold" data-metric="users_total">{{ $metric('users_total') }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="text-muted small">{{ __('إعلانات بانتظار المراجعة') }}</div>
                                    <div class="fw-semibold" data-metric="items_pending">{{ $metric('items_pending') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @php
            $events = collect($recentItems)
                ->map(function ($item) {
                    return [
                        'type' => 'item',
                        'title' => $item->name,
                        'status' => $item->status,
                        'created_at' => $item->created_at,
                    ];
                })
                ->merge(
                    collect($recentPayments)->map(function ($payment) {
                        return [
                            'type' => 'payment',
                            'title' => __('دفعة #:id', ['id' => $payment->id]),
                            'status' => $payment->payment_status,
                            'amount' => $payment->amount,
                            'currency' => $payment->currency,
                            'created_at' => $payment->created_at,
                        ];
                    })
                )
                ->merge(
                    collect($recentManualRequests)->map(function ($manual) {
                        return [
                            'type' => 'manual',
                            'title' => __('طلب يدوي #:id', ['id' => $manual->id]),
                            'status' => $manual->status,
                            'amount' => $manual->amount,
                            'currency' => $manual->currency,
                            'created_at' => $manual->created_at,
                        ];
                    })
                )
                ->sortByDesc('created_at')
                ->take(15);
        @endphp

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">{{ __('بث النشاط المباشر') }}</h5>
                        <span class="text-muted small">{{ __('آخر الإعلانات والمدفوعات والطلبات اليدوية') }}</span>
                    </div>
                    <div class="card-body">
                        <div id="live-feed" class="list-group list-group-flush">
                            @forelse($events as $event)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold">{{ $event['title'] }}</div>
                                        <div class="text-muted small">
                                            @switch($event['type'])
                                                @case('item')
                                                    <span class="badge bg-primary-subtle text-primary">{{ __('إعلان') }}</span>
                                                    @break
                                                @case('payment')
                                                    <span class="badge bg-info-subtle text-info">{{ __('مدفوعات') }}</span>
                                                    @break
                                                @case('manual')
                                                    <span class="badge bg-secondary-subtle text-secondary">{{ __('طلب يدوي') }}</span>
                                                    @break
                                            @endswitch
                                            • {{ $event['status'] ?? __('غير محدد') }}
                                            @if (!empty($event['amount']))
                                                • {{ $event['amount'] }} {{ $event['currency'] }}
                                            @endif
                                        </div>
                                    </div>
                                    <span class="text-muted small">{{ optional($event['created_at'])->diffForHumans() }}</span>
                                </div>
                            @empty
                                <div class="text-center text-muted py-3">{{ __('لا توجد أحداث حديثة') }}</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">{{ __('آخر المستخدمين المنضمين') }}</h5>
                        <span class="text-muted small">{{ __('تحديث تلقائي') }}</span>
                    </div>
                    <div class="card-body">
                        <div id="recent-users" class="list-group list-group-flush">
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

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const metricsUrl = '{{ route('home.metrics') }}';
            const metricEls = document.querySelectorAll('[data-metric]');
            const feedContainer = document.getElementById('live-feed');
            const recentUsersContainer = document.getElementById('recent-users');

            const chart = new ApexCharts(document.querySelector('#live-activity-chart'), {
                chart: {
                    type: 'area',
                    height: 320,
                    toolbar: { show: false },
                    animations: { enabled: true }
                },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3 },
                series: @json($timeline['series'] ?? []),
                xaxis: { categories: @json($timeline['labels'] ?? []) },
                colors: ['#4e79ff', '#00c2a8', '#ffa534'],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.35,
                        opacityTo: 0.05,
                        stops: [0, 90, 100]
                    }
                },
                legend: { position: 'top' },
                yaxis: { labels: { formatter: val => parseInt(val, 10) } },
                tooltip: { shared: true }
            });

            chart.render();

            const renderFeed = (payload) => {
                if (!feedContainer) return;
                const events = [];

                (payload.items || []).forEach((item) => {
                    events.push({
                        type: 'item',
                        title: item.name || '{{ __('إعلان') }}',
                        status: item.status,
                        amount: null,
                        currency: null,
                        created_at: item.created_at
                    });
                });

                (payload.payments || []).forEach((payment) => {
                    events.push({
                        type: 'payment',
                        title: payment.id ? `{{ __('دفعة') }} #${payment.id}` : '{{ __('دفعة') }}',
                        status: payment.payment_status,
                        amount: payment.amount,
                        currency: payment.currency,
                        created_at: payment.created_at
                    });
                });

                (payload.manuals || []).forEach((manual) => {
                    events.push({
                        type: 'manual',
                        title: manual.id ? `{{ __('طلب يدوي') }} #${manual.id}` : '{{ __('طلب يدوي') }}',
                        status: manual.status,
                        amount: manual.amount,
                        currency: manual.currency,
                        created_at: manual.created_at
                    });
                });

                events.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
                const latest = events.slice(0, 15);

                feedContainer.innerHTML = latest.length
                    ? latest.map((event) => {
                        const badge = event.type === 'item'
                            ? '<span class="badge bg-primary-subtle text-primary">{{ __('إعلان') }}</span>'
                            : event.type === 'payment'
                                ? '<span class="badge bg-info-subtle text-info">{{ __('مدفوعات') }}</span>'
                                : '<span class="badge bg-secondary-subtle text-secondary">{{ __('طلب يدوي') }}</span>';

                        const time = event.created_at ? new Date(event.created_at).toLocaleString('ar-EG') : '—';
                        const amount = event.amount ? ` • ${event.amount} ${event.currency || ''}` : '';
                        const status = event.status || '{{ __('غير محدد') }}';

                        return `
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold">${event.title || ''}</div>
                                    <div class="text-muted small">${badge} • ${status}${amount}</div>
                                </div>
                                <span class="text-muted small">${time}</span>
                            </div>
                        `;
                    }).join('')
                    : `<div class="text-center text-muted py-3">{{ __('لا توجد أحداث حديثة') }}</div>`;
            };

            const renderUsers = (users) => {
                if (!recentUsersContainer) return;
                if (!users.length) {
                    recentUsersContainer.innerHTML = `<div class="text-center text-muted py-3">{{ __('لا يوجد مستخدمون جدد') }}</div>`;
                    return;
                }

                recentUsersContainer.innerHTML = users.map((user) => {
                    const name = user.name || '{{ __('بدون اسم') }}';
                    const email = user.email || '';
                    const time = user.created_at ? new Date(user.created_at).toLocaleString('ar-EG') : '—';

                    return `
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold">${name}</div>
                                <div class="text-muted small">${email}</div>
                            </div>
                            <span class="text-muted small">${time}</span>
                        </div>
                    `;
                }).join('');
            };

            renderFeed({
                items: @json($recentItems),
                payments: @json($recentPayments),
                manuals: @json($recentManualRequests),
            });
            renderUsers(@json($recentUsers));

            const refreshMetrics = async () => {
                try {
                    const response = await fetch(metricsUrl, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) {
                        return;
                    }

                    const data = await response.json();

                    if (data.counts) {
                        metricEls.forEach((el) => {
                            const key = el.dataset.metric;
                            if (data.counts.hasOwnProperty(key)) {
                                el.textContent = data.counts[key] ?? '—';
                            }
                        });
                    }

                    if (data.timeline) {
                        chart.updateOptions({
                            series: data.timeline.series || [],
                            xaxis: { categories: data.timeline.labels || [] }
                        });
                    }

                    renderFeed({
                        items: data.recentItems || [],
                        payments: data.recentPayments || [],
                        manuals: data.recentManualRequests || [],
                    });

                    if (data.recentUsers) {
                        renderUsers(data.recentUsers);
                    }
                } catch (error) {
                    console.warn('metrics refresh failed', error);
                }
            };

            setInterval(refreshMetrics, 30000);
        });
    </script>
@endpush
