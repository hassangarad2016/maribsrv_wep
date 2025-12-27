@extends('layouts.main')


@php
    use App\Models\ManualPaymentRequest;
@endphp



@section('title', 'تفاصيل الطلب #' . $order->order_number)

@section('content')
<div class="container-fluid order-details-page">
    @php
        $deliverySummary = $order->delivery_payment_summary ?? [];
        $paymentSummary = $order->payment_summary ?? [];
        $manualPaymentStatusLabels = [
            ManualPaymentRequest::STATUS_PENDING => 'قيد الانتظار',
            ManualPaymentRequest::STATUS_UNDER_REVIEW => 'قيد المراجعة',
            ManualPaymentRequest::STATUS_APPROVED => 'مدفوع (يدوي)',
            ManualPaymentRequest::STATUS_REJECTED => 'مرفوض',
        ];
        $manualPaymentStatusBadgeClasses = [
            ManualPaymentRequest::STATUS_PENDING => 'bg-warning text-dark',
            ManualPaymentRequest::STATUS_UNDER_REVIEW => 'bg-warning text-dark',
            ManualPaymentRequest::STATUS_APPROVED => 'bg-success',
            ManualPaymentRequest::STATUS_REJECTED => 'bg-danger',
        ];
        $manualPaymentStatusAlertClasses = [
            ManualPaymentRequest::STATUS_PENDING => 'alert-warning',
            ManualPaymentRequest::STATUS_UNDER_REVIEW => 'alert-warning',
            ManualPaymentRequest::STATUS_APPROVED => 'alert-success',
            ManualPaymentRequest::STATUS_REJECTED => 'alert-danger',
        ];
        $latestManualPaymentRequest = $latestManualPaymentRequest ?? ($order->manualPaymentRequests->first() ?? null);

        $manualPaymentLocked = $pendingManualPaymentRequest !== null;

        $pricingSnapshot = is_array($order->pricing_snapshot) ? $order->pricing_snapshot : [];
        $policyData = data_get($pricingSnapshot, 'policy');
        $policyId = data_get($pricingSnapshot, 'policy_id', data_get($policyData, 'id'));
        $policyCode = data_get($policyData, 'code');
        $addressSnapshot = is_array($order->address_snapshot) ? $order->address_snapshot : [];

        $shippingAddressRaw = $order->shipping_address;
        $shippingAddressData = null;
        $shippingAddressDisplay = null;

        if (is_string($shippingAddressRaw) && $shippingAddressRaw !== '') {
            $decodedShippingAddress = json_decode($shippingAddressRaw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedShippingAddress)) {
                $shippingAddressData = $decodedShippingAddress;
            } else {
                $shippingAddressDisplay = trim($shippingAddressRaw);
            }
        } elseif (is_array($shippingAddressRaw)) {
            $shippingAddressData = $shippingAddressRaw;
        }

        if (is_array($shippingAddressData)) {
            $shippingAddressDisplay = collect($shippingAddressData)
                ->filter(static fn ($value) => is_string($value) && trim($value) !== '')
                ->implode('طŒ ');
        }

        $coordinateSources = array_values(array_filter([
            $addressSnapshot !== [] ? $addressSnapshot : null,
            is_array($shippingAddressData) ? $shippingAddressData : null,
        ]));

        $latitude = null;
        $longitude = null;

        $latitudeKeys = ['latitude', 'lat', 'coords.latitude', 'coords.lat'];
        $longitudeKeys = ['longitude', 'lng', 'lon', 'coords.longitude', 'coords.lng', 'coords.lon'];

        foreach ($coordinateSources as $source) {
            foreach ($latitudeKeys as $key) {
                $candidate = data_get($source, $key);

                if (is_string($candidate) || is_numeric($candidate)) {
                    $trimmed = trim((string) $candidate);

                    if ($trimmed !== '') {
                        $latitude = (float) $trimmed;
                        break 2;
                    }
                }
            }
        }

        foreach ($coordinateSources as $source) {
            foreach ($longitudeKeys as $key) {
                $candidate = data_get($source, $key);

                if (is_string($candidate) || is_numeric($candidate)) {
                    $trimmed = trim((string) $candidate);

                    if ($trimmed !== '') {
                        $longitude = (float) $trimmed;
                        break 2;
                    }
                }
            }
        }

        $mapUrl = null;
        $mapUrlKeys = ['map_url', 'map_link', 'maps_url', 'google_maps_url', 'google_map', 'location_url'];

        foreach ($coordinateSources as $source) {
            foreach ($mapUrlKeys as $key) {
                $candidate = data_get($source, $key);

                if (is_string($candidate)) {
                    $trimmed = trim($candidate);

                    if ($trimmed !== '') {
                        $mapUrl = $trimmed;
                        break 2;
                    }
                }
            }
        }

        $hasCoordinates = $latitude !== null && $longitude !== null;
        $coordinateDisplay = $hasCoordinates
            ? number_format($latitude, 6) . ', ' . number_format($longitude, 6)
            : null;

        $addressCopyParts = collect([
            $shippingAddressDisplay,
            $coordinateDisplay ? 'الإحداثيات: ' . $coordinateDisplay : null,
            $mapUrl ? 'الخريطة: ' . $mapUrl : null,
        ])
            ->filter(static fn ($value) => is_string($value) && trim($value) !== '')
            ->unique()
            ->values();

        $addressCopyText = $addressCopyParts->implode(PHP_EOL);

        $mapsQuery = $hasCoordinates ? $latitude . ',' . $longitude : null;
        $googleMapsUrl = $mapsQuery !== null
            ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($mapsQuery)
            : ($mapUrl ?? null);
        $appleMapsUrl = $mapsQuery !== null
            ? 'https://maps.apple.com/?ll=' . $mapsQuery . '&q=' . urlencode($shippingAddressDisplay ?? 'Location')
            : null;

        $shippingAddressDisplay = $shippingAddressDisplay ?: ($order->shipping_address ?: null);

        if ($shippingAddressDisplay === null || $shippingAddressDisplay === '') {
            $snapshotDisplay = collect([
                data_get($addressSnapshot, 'label'),
                data_get($addressSnapshot, 'street'),
                data_get($addressSnapshot, 'building'),
                data_get($addressSnapshot, 'apartment'),
                data_get($addressSnapshot, 'city'),
                data_get($addressSnapshot, 'area'),
                data_get($addressSnapshot, 'note'),
                data_get($addressSnapshot, 'instructions'),
            ])
                ->filter(static fn ($value) => is_string($value) && trim($value) !== '')
                ->unique()
                ->implode('طŒ ');

            if ($snapshotDisplay !== '') {
                $shippingAddressDisplay = $snapshotDisplay;
            }
        }

        $cartSnapshot = is_array($order->cart_snapshot) ? $order->cart_snapshot : [];
        $statusHistoryEntries = collect($order->status_history ?? []);
        $trackingDetails = is_array($order->tracking_details) ? $order->tracking_details : null;
        $trackingProof = is_array($trackingDetails['proof'] ?? null) ? $trackingDetails['proof'] : [];
        $statusDisplayMap = \App\Models\Order::statusDisplayMap();

        $depositReceipts = is_array($order->deposit_receipts) ? $order->deposit_receipts : [];
        $hasDepositReceipts = ! empty($depositReceipts);
        $canDownloadInvoice = method_exists($order, 'hasOutstandingBalance') ? ! $order->hasOutstandingBalance() : true;

    @endphp


    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert" data-testid="order-success-alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
    @php
        $currencyCode = strtoupper((string) ($order->currency ?? config('app.currency', 'YER')));
        $statusCollection = $orderStatuses instanceof \Illuminate\Support\Collection
            ? $orderStatuses
            : collect($orderStatuses ?? []);
        $heroMatchedStatus = $statusCollection->firstWhere('code', $order->order_status);
        $heroStatusDisplayEntry = $statusDisplayMap[$order->order_status] ?? null;
        $heroStatusLabel = \App\Models\Order::statusLabel($order->order_status);
        if ($heroStatusLabel === '') {
            $heroStatusLabel = ($statusLabels ?? [])[$order->order_status]
                ?? optional($heroMatchedStatus)->name
                ?? (is_array($heroStatusDisplayEntry) ? ($heroStatusDisplayEntry['label'] ?? null) : null)
                ?? \Illuminate\Support\Str::of($order->order_status)->replace('_', ' ')->headline();
        }
        $heroStatusColor = optional($heroMatchedStatus)->color ?: '#6c757d';
        $heroStatusIcon = \App\Models\Order::statusIcon($order->order_status);
        if (! $heroStatusIcon && is_array($heroStatusDisplayEntry)) {
            $heroStatusIcon = $heroStatusDisplayEntry['icon'] ?? null;
        }

        $heroManualStatus = $latestManualPaymentRequest?->status;
        $heroPaymentLabels = \App\Models\Order::paymentStatusLabels();
        if ($heroManualStatus !== null) {
            $heroPaymentStatusLabel = $manualPaymentStatusLabels[$heroManualStatus] ?? 'غير محدد';
            $heroPaymentStatusClass = $manualPaymentStatusBadgeClasses[$heroManualStatus] ?? 'bg-secondary';
        } else {
            $heroPaymentStatusLabel = $heroPaymentLabels[$order->payment_status] ?? ($order->payment_status ?: 'غير محدد');
            $heroPaymentStatusClass = match ($order->payment_status) {
                'paid', 'success', 'succeed', 'completed', 'captured' => 'bg-success',
                'pending' => 'bg-warning text-dark',
                'partial', 'payment_partial' => 'bg-primary',
                'refunded' => 'bg-info',
                'failed', 'canceled', 'cancelled' => 'bg-danger',
                default => 'bg-secondary',
            };
        }

        $heroCustomerName = optional($order->user)->name ?? '-';
        $heroSellerName = optional($order->seller)->name ?? '-';
        $heroItemsCount = $order->items->count();
    @endphp




        <div class="order-details-hero">
        <div class="order-details-info">
            <div class="order-details-kicker">رقم الطلب #{{ $order->order_number }}</div>
            <h2 class="order-details-title">تفاصيل الطلب</h2>
            <div class="order-details-meta">
                <span class="badge d-inline-flex align-items-center gap-1" style="background-color: {{ $heroStatusColor }}">
                    @if($heroStatusIcon)
                        <i class="{{ $heroStatusIcon }}"></i>
                    @endif
                    {{ $heroStatusLabel }}
                </span>
                <span class="badge {{ $heroPaymentStatusClass }}">{{ $heroPaymentStatusLabel }}</span>
                <span class="text-muted">
                    <i class="bi bi-calendar3"></i> {{ $order->created_at->format('Y-m-d H:i') }}
                </span>
            </div>
        </div>
        <div class="order-details-actions">
            @php
                $showReserve = request()->boolean('include_reserve_statuses');
                $reserveToggleUrl = request()->fullUrlWithQuery(['include_reserve_statuses' => $showReserve ? 0 : 1]);
            @endphp

            @if ($canDownloadInvoice)
                <a href="{{ route('orders.invoice.pdf', $order->id) }}" target="_blank" class="btn btn-outline-primary" data-testid="invoice-button">
                    <i class="fa fa-file-invoice"></i> تحميل الفاتورة
                </a>
            @else
                <button type="button" class="btn btn-outline-primary disabled" data-testid="invoice-button" title="{{ __('orders.invoice.balance_outstanding') }}" disabled>
                    <i class="fa fa-file-invoice"></i> تحميل الفاتورة</button>
            @endif

            @if ($hasDepositReceipts)
                <a href="{{ route('orders.deposit-receipts', $order->id) }}" target="_blank" class="btn btn-outline-info" data-testid="deposit-receipt-button">
                    <i class="fa fa-receipt"></i> إيصال الدفع
                </a>
            @endif

            @if($manualPaymentLocked)
                <span class="btn btn-outline-success disabled" aria-disabled="true" title="لا يمكن إضافة الطلب إلى مجموعة أثناء مراجعة الدفع" data-testid="add-to-payment-group-button">
                    <i class="fa fa-layer-group"></i> إضافة إلى مجموعة الدفع
                </span>
            @else
                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addOrderToGroupModal" data-testid="add-to-payment-group-button">
                    <i class="fa fa-layer-group"></i> إضافة إلى مجموعة الدفع
                </button>
            @endif

            @if($manualPaymentLocked)
                <span class="btn btn-outline-warning disabled" aria-disabled="true" title="لا يمكن إرسال إشعار فوري أثناء مراجعة الدفع" data-testid="instant-notification-button">
                    <i class="fa fa-bell"></i> إرسال إشعار فوري
                </span>
            @else
                <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#instantNotificationModal" data-testid="instant-notification-button">
                    <i class="fa fa-bell"></i> إرسال إشعار فوري
                </button>
            @endif

            <a href="{{ $reserveToggleUrl }}" class="btn btn-outline-secondary">
                <i class="bi {{ $showReserve ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                {{ $showReserve ? 'إخفاء الحالات المحجوزة' : 'عرض الحالات المحجوزة' }}
            </a>

            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#orderUpdateModal">
                <i class="fa fa-edit"></i> تعديل الطلب
            </button>
        </div>
    </div>

    <div class="order-metrics">
        <div class="order-metric-card">
            <div>
                <div class="order-metric-label">إجمالي الطلب</div>
                <div class="order-metric-value">{{ number_format($order->final_amount, 2) }} {{ $currencyCode }}</div>
            </div>
            <div class="order-metric-icon">
                <i class="bi bi-cash-coin"></i>
            </div>
        </div>
        <div class="order-metric-card">
            <div>
                <div class="order-metric-label">عدد العناصر</div>
                <div class="order-metric-value">{{ $heroItemsCount }}</div>
            </div>
            <div class="order-metric-icon">
                <i class="bi bi-bag-check"></i>
            </div>
        </div>
        <div class="order-metric-card">
            <div>
                <div class="order-metric-label">العميل</div>
                <div class="order-metric-value">{{ $heroCustomerName }}</div>
                @if(optional($order->user)->mobile)
                    <div class="order-metric-sub">{{ $order->user->mobile }}</div>
                @endif
            </div>
            <div class="order-metric-icon">
                <i class="bi bi-person"></i>
            </div>
        </div>
        <div class="order-metric-card">
            <div>
                <div class="order-metric-label">التاجر</div>
                <div class="order-metric-value">{{ $heroSellerName }}</div>
                @if(optional($order->seller)->mobile)
                    <div class="order-metric-sub">{{ $order->seller->mobile }}</div>
                @endif
            </div>
            <div class="order-metric-icon">
                <i class="bi bi-shop"></i>
            </div>
        </div>
    </div>
    </div>


    <div class="row">
        <div class="col-12 mb-4">
            <div class="card order-overview-card">
                <div class="card-header">
                    <h4 class="card-title">معلومات الطلب</h4>
                </div>
                <div class="card-body">
                    @php
                        $statusCollection = $orderStatuses instanceof \Illuminate\Support\Collection ? $orderStatuses : collect($orderStatuses);
                        $matchedStatus = $statusCollection->firstWhere('code', $order->order_status);
                        $statusDisplayEntry = $statusDisplayMap[$order->order_status] ?? null;

                        $statusColor = optional($matchedStatus)->color ?? '#777777';
                        $statusLabel = \App\Models\Order::statusLabel($order->order_status);
                        if ($statusLabel === '') {
                            $statusLabel = $statusLabels[$order->order_status]
                                ?? optional($matchedStatus)->name
                                ?? (is_array($statusDisplayEntry) ? ($statusDisplayEntry['label'] ?? null) : null)
                                ?? \Illuminate\Support\Str::of($order->order_status)->replace('_', ' ')->headline();
                        }

                        $statusIconClass = \App\Models\Order::statusIcon($order->order_status);
                        if (! $statusIconClass && is_array($statusDisplayEntry)) {
                            $statusIconClass = $statusDisplayEntry['icon'] ?? null;
                        }

                        $statusTimelineMessage = \App\Models\Order::statusTimelineMessage($order->order_status)
                            ?? (is_array($statusDisplayEntry) ? ($statusDisplayEntry['timeline'] ?? null) : null);


                        $isReserveStatus = (bool) optional($matchedStatus)->is_reserve
                            || (bool) (is_array($statusDisplayEntry) ? ($statusDisplayEntry['reserve'] ?? false) : false);

                        $statusBadgeTitle = $statusTimelineMessage ?? '';
                        if ($isReserveStatus) {
                            $statusBadgeTitle = trim('مرحلة احتياطية' . ($statusBadgeTitle !== '' ? ' - ' . $statusBadgeTitle : ''));
                        }



                        $categoryBadges = $order->items
                            ->map(function ($orderItem) {
                                return optional(optional($orderItem->item)->category)->name;
                            })
                            ->filter()
                            ->unique()
                            ->values();

                        $timingValue = $order->delivery_payment_timing ?? ($deliverySummary['timing'] ?? null);
                        $deliveryStatusValue = $order->delivery_payment_status ?? ($deliverySummary['status'] ?? null);
                        $timingLabel = $timingValue
                            ? ($deliveryPaymentTimingLabels[$timingValue] ?? \Illuminate\Support\Str::of($timingValue)->replace('_', ' ')->headline())
                            : 'غير محدد';
                        $deliveryStatusLabel = $deliveryStatusValue
                            ? ($deliveryPaymentStatusLabels[$deliveryStatusValue] ?? \Illuminate\Support\Str::of($deliveryStatusValue)->replace('_', ' ')->headline())
                            : 'غير محدد';
                        $deliveryStatusClass = match ($deliveryStatusValue) {
                            'paid' => 'bg-success',
                            'pending' => 'bg-warning',
                            'waived' => 'bg-secondary',
                            'due_on_delivery', 'due_now' => 'bg-info',
                            'partial' => 'bg-primary',
                            'failed' => 'bg-danger',
                            default => 'bg-secondary'
                        };
                        $onlinePayable = $deliverySummary['online_payable'] ?? null;
                        $codDue = $deliverySummary['cod_due'] ?? null;


                        $trackingCarrier = $trackingDetails['carrier_name'] ?? null;
                        $trackingNumber = $trackingDetails['tracking_number'] ?? null;
                        $trackingUrl = $trackingDetails['tracking_url'] ?? null;
                        $trackingImagePath = $trackingProof['image_path'] ?? null;
                        $trackingImageUrl = $trackingImagePath ? (filter_var($trackingImagePath, FILTER_VALIDATE_URL) ? $trackingImagePath : null) : null;
                        $trackingSignaturePath = $trackingProof['signature_path'] ?? null;
                        $trackingSignatureUrl = $trackingSignaturePath ? (filter_var($trackingSignaturePath, FILTER_VALIDATE_URL) ? $trackingSignaturePath : null) : null;
                        $trackingOtpCode = $trackingProof['otp_code'] ?? null;

                        $deliverySizeValue = $order->delivery_size;
                        $deliverySizeLabel = match ($deliverySizeValue) {
                            null, '' => 'غير محدد',
                            'small' => 'صغير',
                            'medium' => 'متوسط',
                            'large' => 'كبير',
                            default => $deliverySizeValue,
                        };
                        $deliveryDistanceDisplay = $order->delivery_distance ? number_format($order->delivery_distance, 2) . ' كم' : 'غير محددة';
                        $deliveryPriceDisplay = $order->delivery_price ? number_format($order->delivery_price, 2) . ' ريال' : 'غير محدد';
                        $completedAtDisplay = $order->completed_at ? $order->completed_at->format('Y-m-d H:i') : 'غير مكتمل';

                        $latestManualPaymentRequest = $latestManualPaymentRequest ?? $order->manualPaymentRequests->first();
                        $manualPaymentStatus = $latestManualPaymentRequest?->status;
                        $manualPaymentStatusLabel = $manualPaymentStatus
                            ? ($manualPaymentStatusLabels[$manualPaymentStatus] ?? 'غير محدد')
                            : null;
                        $manualPaymentBadgeClass = $manualPaymentStatus
                            ? ($manualPaymentStatusBadgeClasses[$manualPaymentStatus] ?? 'bg-secondary')
                            : null;


                        $paymentStatusValue = $order->payment_status;
                        if ($manualPaymentStatusLabel !== null) {
                            $paymentStatusLabel = $manualPaymentStatusLabel;
                            $paymentStatusBadgeClass = $manualPaymentBadgeClass ?? 'bg-secondary';
                        } else {
                            $paymentStatusLabel = 'غير محدد';
                            $paymentStatusBadgeClass = 'bg-secondary';
                            if ($paymentStatusValue === 'pending') {
                                $paymentStatusLabel = 'قيد الانتظار';
                            } elseif ($paymentStatusValue === 'paid') {
                                $paymentStatusLabel = 'مدفوع';
                                $paymentStatusBadgeClass = 'bg-success';
                            } elseif ($paymentStatusValue === 'refunded') {
                                $paymentStatusLabel = 'مسترجع';
                                $paymentStatusBadgeClass = 'bg-warning';
                            } elseif (! empty($paymentStatusValue)) {
                                $paymentStatusLabel = $paymentStatusValue;
                            }
                        }



                        $orderNotes = $order->notes !== null ? trim((string) $order->notes) : '';
                        $availablePaymentGroups = $availablePaymentGroups instanceof \Illuminate\Support\Collection
                            ? $availablePaymentGroups
                            : collect($availablePaymentGroups ?? []);
    @endphp

                    <div class="order-summary-grid">
@if($orderNotes !== '')
                            <section class="order-summary-block order-summary-block--wide">
                                <h6 class="order-summary-heading">ملاحظات الطلب</h6>
                                <p class="order-notes-text mb-0">{{ $orderNotes }}</p>
                            </section>


                        @endif
                    </div>

                    <div class="order-items-section mt-4">
                        <h5 class="order-section-heading">عناصر الطلب</h5>
                        <div class="order-items-grid">
                            @forelse($orderItemsDisplayData as $item)
                                <article class="order-item-card">
                                    <div class="order-item-header">
                                        <img src="{{ $item['thumbnail_url'] }}" alt="صورة المنتج" class="order-item-thumb">
                                        <div class="order-item-header-body">
                                            <div class="order-item-title">{{ $item['name'] ?? 'منتج بدون اسم' }}</div>
                                            @if(! empty($item['variant_label']))
                                                <div class="order-item-variant text-muted small">{{ $item['variant_label'] }}</div>
                                            @endif
                                        </div>
                                        <div class="order-item-actions d-flex flex-column gap-2">
                                            @if($item['product_url'])
                                                <a href="{{ $item['product_url'] }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                                                    <i class="bi bi-box-arrow-up-right"></i>
                                                    عرض المنتج
                                                </a>
                                            @endif
                                            @if(! empty($item['review_url']))
                                                <a href="{{ $item['review_url'] }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                                                    <i class="bi bi-link-45deg"></i>
                                                    التوجيه الى شي ان
                                                </a>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="order-item-meta">
                                        @if(! empty($item['item_id']))
                                            <div>
                                                <span class="order-summary-label">رقم المنتج</span>
                                                <span class="order-summary-value">{{ $item['item_id'] }}</span>
                                            </div>
                                        @endif
                                        @if(! empty($item['variant_id']))
                                            <div>
                                                <span class="order-summary-label">رقم المتغير</span>
                                                <span class="order-summary-value">{{ $item['variant_id'] }}</span>
                                            </div>
                                        @endif
                                        <div>
                                            <span class="order-summary-label">السعر</span>
                                            <span class="order-summary-value">{{ number_format($item['price'], 2) }}</span>
                                        </div>
                                        <div>
                                            <span class="order-summary-label">الكمية</span>
                                            <span class="order-summary-value">{{ rtrim(rtrim(number_format($item['quantity'], 3, '.', ''), '0'), '.') }}</span>
                                        </div>
                                        <div>
                                            <span class="order-summary-label">الإجمالي</span>
                                            <span class="order-summary-value">{{ number_format($item['subtotal'], 2) }}</span>
                                        </div>
                                        @if(! empty($item['currency']))
                                            <div>
                                                <span class="order-summary-label">العملة</span>
                                                <span class="order-summary-value">{{ $item['currency'] }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="order-item-details">
                                        <div class="order-item-section">
                                            <h6 class="order-summary-subheading">خيارات المقاس/اللون</h6>
                                            @if($item['has_options'])
                                                <ul class="order-summary-list order-summary-list--compact mb-0">
                                                    @foreach($item['options'] as $option)
                                                        @php
                                                            $optionValue = $option['value'] ?? null;
                                                            $optionRaw = $option['raw_value'] ?? $optionValue;
                                                            $isColor = $option['is_color'] ?? false;
                                                            $isSize = $option['is_size'] ?? false;
                                                            $colorValue = $option['color_value'] ?? null;
                                                        @endphp
                                                        <li>
                                                            <span class="order-summary-label">{{ $option['label'] }}</span>
                                                            <span class="order-summary-value">
                                                                @if($isColor && $colorValue)
                                                                    <span class="order-color-swatch" style="--color: {{ $colorValue }};" title="{{ $optionRaw }}"></span>
                                                                    @if($optionValue)
                                                                        <span class="order-option-text">{{ $optionValue }}</span>
                                                                    @endif
                                                                @elseif($isSize)
                                                                    <span class="order-size-badge">{{ $optionValue ?? $optionRaw }}</span>
                                                                @else
                                                                    {{ $optionValue ?? $optionRaw }}
                                                                @endif
                                                            </span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <p class="text-muted small mb-0">لا توجد خيارات.</p>
                                            @endif

                                        </div>

                                        <div class="order-item-section">
                                            <h6 class="order-summary-subheading">بيانات الإعلان</h6>
                                            @if($item['has_advertiser'])
                                                <ul class="order-summary-list order-summary-list--compact mb-0">
                                                    @foreach($item['advertiser'] as $field)
                                                        <li>
                                                            <span class="order-summary-label">{{ $field['label'] }}</span>
                                                            <span class="order-summary-value">{{ $field['value'] }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <span class="text-muted">لا يوجد بيانات.</span>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <div class="order-empty-state">
                                    <p class="text-muted mb-0">لا توجد عناصر.</p>
                                </div>
                            @endforelse
                             
                        </div>
  
                        <div class="order-items-summary mt-4">
                            <div>
                                <span class="order-summary-label">الإجمالي</span>
                                <span class="order-summary-value">{{ number_format($order->total_amount, 2) }}</span>
                            </div>
                            <div>
                                <span class="order-summary-label">الضريبة (15%)</span>
                                <span class="order-summary-value">{{ number_format($order->tax_amount, 2) }}</span>
                            </div>
                            <div>
                                <span class="order-summary-label">الخصم</span>
                                <span class="order-summary-value">{{ number_format($order->discount_amount, 2) }}</span>
                            </div>
                            <div>
                                <span class="order-summary-label">الإجمالي النهائي</span>
                                <span class="order-summary-value">{{ number_format($order->final_amount, 2) }}</span>
                            </div>
                        </div>
                </div>
            </div>
        </div>
    </div>



    <!-- Order update modal -->
    <div class="modal fade" id="orderUpdateModal" tabindex="-1" aria-labelledby="orderUpdateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderUpdateModalLabel">تحديث حالة الطلب</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @php
                        $stepDefinitions = [
                            ['code' => \App\Models\Order::STATUS_CONFIRMED, 'label' => 'تم استلام الطلب', 'icon' => 'bi bi-clipboard-check'],
                            ['code' => \App\Models\Order::STATUS_PROCESSING, 'label' => 'قيد المعالجة', 'icon' => 'bi bi-gear'],
                            ['code' => \App\Models\Order::STATUS_PREPARING, 'label' => 'قيد الشحن', 'icon' => 'bi bi-box-seam'],
                            ['code' => \App\Models\Order::STATUS_OUT_FOR_DELIVERY, 'label' => 'في الطريق', 'icon' => 'bi bi-truck'],
                            ['code' => \App\Models\Order::STATUS_DELIVERED, 'label' => 'تم التوصيل', 'icon' => 'bi bi-check-circle'],
                        ];
                        $stepCodes = array_column($stepDefinitions, 'code');
                        $currentStepIndex = array_search($order->order_status, $stepCodes, true);
                        if ($currentStepIndex === false) {
                            $currentStepIndex = 0;
                        }

                        $orderStatusLocked = isset($pendingManualPaymentRequest) && $pendingManualPaymentRequest;
                        $canChangeOrderStatus = $order->hasSuccessfulPayment() && ! $orderStatusLocked;
                        $orderStatusLockMessage = null;
                        if (! $order->hasSuccessfulPayment()) {
                            $orderStatusLockMessage = 'لا يمكن تعديل حالة الطلب قبل اكتمال عملية الدفع.';
                        } elseif ($orderStatusLocked) {
                            $orderStatusLockMessage = 'لا يمكن تعديل حالة الطلب حالياً بسبب طلب دفع يدوي.';
                        }

                        $statusLabelForUpdate = \App\Models\Order::statusLabel($order->order_status);
                        if ($statusLabelForUpdate === '') {
                            $statusLabelForUpdate = optional($orderStatuses->firstWhere('code', $order->order_status))->name
                                ?? \Illuminate\Support\Str::of($order->order_status)->replace('_', ' ')->headline();
                        }
                    @endphp

                    <div class="order-status-steps mb-4">
                        @foreach($stepDefinitions as $index => $step)
                            @php
                                $stepClass = $index < $currentStepIndex ? 'is-complete' : ($index === $currentStepIndex ? 'is-active' : '');
                            @endphp
                            <div class="order-status-step {{ $stepClass }}">
                                <span class="order-status-icon"><i class="{{ $step['icon'] }}"></i></span>
                                <span class="order-status-label">{{ $step['label'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if($canChangeOrderStatus)
                        <form action="{{ route('orders.update', $order->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="form-group mb-3">
                                <label for="modal_order_status" class="form-label">حالة الطلب</label>
                                <select class="form-control" id="modal_order_status" name="order_status" required>
                                    @foreach($orderStatuses as $status)
                                        <option value="{{ $status->code }}" {{ $order->order_status == $status->code ? 'selected' : '' }}>
                                            {{ $status->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group mb-0">
                                <label for="modal_comment" class="form-label">ملاحظة التحديث</label>
                                <textarea class="form-control" id="modal_comment" name="comment" rows="3"></textarea>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-3">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                                <button type="submit" class="btn btn-primary">تحديث</button>
                            </div>
                        </form>
                    @else
                        <div class="form-group mb-3">
                            <label class="form-label">حالة الطلب</label>
                            <div class="form-control-plaintext border rounded bg-light px-3 py-2">
                                {{ $statusLabelForUpdate ?? '—' }}
                            </div>
                        </div>
                        @if($orderStatusLockMessage)
                            <div class="alert alert-info mb-0">
                                {{ $orderStatusLockMessage }}
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Add order to payment group modal -->
    <div class="modal fade" id="addOrderToGroupModal" tabindex="-1" aria-labelledby="addOrderToGroupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addOrderToGroupModalLabel">إضافة الطلب إلى مجموعة</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <h6 class="mb-3">المجموعات المتاحة</h6>
                        @forelse ($availablePaymentGroups as $group)
                            <form action="{{ route('orders.payment-groups.orders.store', $group) }}" method="POST" class="border rounded p-3 mb-3" data-testid="add-order-to-group-form-{{ $group->id }}">
                                @csrf
                                <input type="hidden" name="order_ids[]" value="{{ $order->id }}">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                                    <div>
                                        <h5 class="mb-1">{{ $group->name }}</h5>
                                        @if (filled($group->note))
                                            <p class="mb-2 text-muted small">{{ $group->note }}</p>
                                        @endif
                                        <div class="d-flex flex-wrap gap-3 text-muted small">
                                            <span><i class="fa fa-list-ol"></i> {{ number_format($group->orders_count) }} طلب</span>
                                            @if ($group->created_at)
                                                <span><i class="fa fa-calendar"></i> {{ $group->created_at->format('Y-m-d') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-outline-success">
                                        <i class="fa fa-plus"></i> إضافة إلى هذه المجموعة
                                    </button>
                                </div>
                            </form>
                        @empty
                            <p class="text-muted mb-0">لا توجد مجموعات متاحة حالياً لهذا الطلب. يمكنك إنشاء مجموعة جديدة باستخدام النموذج أدناه.</p>
                        @endforelse
                    </div>
                    <div>
                        <h6 class="mb-3">إنشاء مجموعة جديدة</h6>
                        <form action="{{ route('orders.payment-groups.store', $order) }}" method="POST" data-testid="create-payment-group-form">
                            @csrf
                            <div class="form-group">
                                <label for="payment_group_name">اسم المجموعة</label>
                                <input type="text" id="payment_group_name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" maxlength="190" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group mb-0">
                                <label for="payment_group_note">ملاحظة (اختياري)</label>
                                <textarea id="payment_group_note" name="note" rows="3" class="form-control @error('note') is-invalid @enderror" maxlength="1000">{{ old('note') }}</textarea>
                                @error('note')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mt-3 d-flex justify-content-end align-items-center gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-layer-group"></i> إضافة إلى مجموعة الدفع</button>
                            </div>
                            <p class="small text-muted mb-0 mt-2">سيتم إضافة هذا الطلب تلقائياً إلى المجموعة الجديدة بعد إنشائها.</p>
                        </form>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            
            </div>
        </div>
    </div>

    <!-- Instant notification modal -->
    <div class="modal fade" id="instantNotificationModal" tabindex="-1" role="dialog" aria-labelledby="instantNotificationModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="instantNotificationModalLabel">إرسال إشعار فوري للعميل</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('orders.notifications.send', $order) }}" method="POST" data-testid="instant-notification-form">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="instant_notification_title" class="form-label">عنوان الإشعار (اختياري)</label>
                            <input type="text" class="form-control" id="instant_notification_title" name="title" maxlength="190" placeholder="عنوان موجز للإشعار">
                        </div>
                        <div class="form-group mb-0">
                            <label for="instant_notification_message" class="form-label">نص الإشعار</label>
                            <textarea class="form-control" id="instant_notification_message" name="message" rows="4" required placeholder="اكتب الرسالة التي سيتم إرسالها للعميل"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-warning">إرسال الإشعار</button>
                    </div>
                </form>
            </div>
        </div>
    </div>




    <!-- Modal for delete confirmation -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">تأكيد الحذف</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    هل أنت متأكد من حذف الطلب رقم <strong>{{ $order->order_number }}</strong>؟
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <form action="{{ route('orders.destroy', $order->id) }}" method="POST" style="display: inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">حذف</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {


            const successMessage = @json(session('success'));
            if (successMessage && typeof window.showSuccessToast === 'function') {
                window.showSuccessToast(successMessage);
            }



            const copyButtons = document.querySelectorAll('.copy-address-btn[data-address-copy]');

            if (!copyButtons.length) {
                return;
            }

            const fallbackCopyText = function (text) {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', 'readonly');
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();

                let successful = false;

                try {
                    successful = document.execCommand('copy');
                } catch (error) {
                    successful = false;
                }

                document.body.removeChild(textarea);

                return successful;
            };

            const notify = function (success) {
                const successMessage = 'تم نسخ العنوان بنجاح';
                const errorMessage = 'تعذر نسخ العنوان، يرجى النسخ يدويًا';

                if (success && typeof window.showSuccessToast === 'function') {
                    window.showSuccessToast(successMessage);
                } else if (!success && typeof window.showErrorToast === 'function') {
                    window.showErrorToast(errorMessage);
                }
            };

            copyButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const address = button.getAttribute('data-address-copy');

                    if (!address) {
                        return;
                    }

                    if (typeof navigator !== 'undefined' && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                        navigator.clipboard.writeText(address)
                            .then(function () {
                                notify(true);
                            })
                            .catch(function () {
                                const fallbackResult = fallbackCopyText(address);
                                notify(fallbackResult);
                            });

                        return;
                    }

                    const fallbackResult = fallbackCopyText(address);
                    notify(fallbackResult);
                });
            });
        });
    </script>
@endpush


<style>
.step {
    text-align: center;
}
.circle {
    width: 30px;
    height: 30px;
    background: #ddd;
    border-radius: 50%;
    display: inline-block;
    line-height: 30px;
    color: #fff;
    font-weight: bold;
}
.circle.active {
    background: #007bff;
}
.line {
    flex-grow: 1;
    height: 2px;
    background: #ddd;
}
.label {
    font-size: 12px;
    margin-top: 5px;
}

.order-summary-grid {
    display: grid;
    gap: 1.5rem;

}

@media (min-width: 768px) {
    .order-summary-grid {
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    }
}

.order-summary-block {
    background-color: #f8f9fb;
    border: 1px solid #e9ecef;
    border-radius: 0.75rem;
    padding: 1.25rem;
    box-shadow: 0 1px 2px rgba(15, 34, 58, 0.04);
}

.order-summary-block--wide {
    grid-column: 1 / -1;
}

.order-summary-heading {
    font-size: 1.05rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
}

.order-summary-subheading {
    font-size: 0.95rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.order-section-heading {
    font-size: 1.15rem;

    font-weight: 600;
    margin-bottom: 1rem;

}

.order-summary-parties {
    display: grid;
    gap: 1rem;
}

@media (min-width: 576px) {
    .order-summary-parties {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }



}
.order-party-title {
    font-weight: 600;
    margin-bottom: 0.5rem;
}
.order-summary-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;

}
.order-summary-list li {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: baseline;
}

.order-summary-list--compact {
    gap: 0.35rem;
}
.order-summary-label {
    font-weight: 600;
    color: #495057;
    min-width: 120px;
}

.order-summary-value {
    flex: 1 1 auto;
    color: #212529;
    display: inline-flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    align-items: center;
}

.order-address {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.order-address-text {
    font-weight: 600;
}

.order-address-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.order-address-grid {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

.order-address-field {
    background-color: #fff;
    border: 1px dashed #dee2e6;
    border-radius: 0.5rem;
    padding: 0.75rem;
}

.order-items-grid {
    display: grid;
    gap: 1.25rem;
}

@media (min-width: 768px) {
    .order-items-grid {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }
}

.order-item-card {
    background-color: #fff;
    border: 1px solid #e9ecef;
    border-radius: 0.75rem;
    padding: 1rem 1.25rem;
    display: flex;
    flex-direction: column;
    height: 100%;
    box-shadow: 0 1px 3px rgba(15, 34, 58, 0.08);
}



.order-item-header {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}
.order-item-thumb {
    width: 72px;
    height: 72px;
    object-fit: cover;
    border-radius: 0.5rem;
    flex-shrink: 0;

}

.order-item-header-body {
    flex: 1 1 auto;
    min-width: 0;
}

.order-item-title {
    font-weight: 600;
}

.order-item-actions {
    margin-inline-start: auto;
}

.order-item-meta {
    margin-top: 1rem;
    display: grid;
    gap: 0.5rem;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
}

.order-item-details {
    margin-top: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.order-item-section {
    background-color: #f8f9fa;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
}

.order-color-swatch {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    background-color: var(--color);
    border: 1px solid #dee2e6;
    display: inline-block;
    vertical-align: middle;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.6);
}

.order-size-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.6rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
    background: #eef2ff;
    color: #1e3a8a;
}

.order-option-text {
    margin-inline-start: 0.35rem;
}

.order-items-summary {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    background-color: #f1f3f5;
    border: 1px solid #e9ecef;
    border-radius: 0.75rem;
    padding: 1rem 1.25rem;
}

.order-items-summary > div {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    align-items: center;
}

.order-empty-state {
    padding: 1.5rem;
    border: 1px dashed #ced4da;
    border-radius: 0.75rem;
    background-color: #f8f9fa;
    text-align: center;
}

.order-notes-text {
    line-height: 1.6;
}

@media (max-width: 575.98px) {
    .order-summary-label {
        min-width: 0;
    }
}

.order-details-page {
    background: linear-gradient(180deg, rgba(13, 110, 253, 0.06), rgba(13, 110, 253, 0.015));
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 1.25rem;
    padding: 1.25rem;
}
.order-details-hero {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}
.order-details-title {
    margin: 0;
    font-size: 1.45rem;
    font-weight: 700;
    color: #0f172a;
}
.order-details-kicker {
    font-size: 0.9rem;
    color: #6c757d;
    font-weight: 600;
}
.order-details-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    margin-top: 0.4rem;
}
.order-details-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.order-status-steps {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 0.75rem;
    padding: 0.75rem;
    background: #f8f9fb;
    border: 1px solid #e9ecef;
    border-radius: 0.9rem;
}

.order-status-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    text-align: center;
    color: #6c757d;
}

.order-status-icon {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #e9ecef;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: #6c757d;
}

.order-status-step.is-complete .order-status-icon {
    background: rgba(25, 135, 84, 0.15);
    color: #198754;
}

.order-status-step.is-active .order-status-icon {
    background: rgba(13, 110, 253, 0.18);
    color: #0d6efd;
}

.order-status-step.is-complete,
.order-status-step.is-active {
    color: #212529;
    font-weight: 600;
}

.order-status-label {
    font-size: 0.85rem;
}
.order-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}
.order-metric-card {
    background: #ffffff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 0.9rem;
    padding: 0.85rem 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
    min-height: 78px;
}
.order-metric-label {
    font-size: 0.78rem;
    color: #6c757d;
    font-weight: 600;
}
.order-metric-value {
    font-size: 1.05rem;
    font-weight: 700;
    color: #212529;
}
.order-metric-sub {
    font-size: 0.78rem;
    color: #6c757d;
}
.order-metric-icon {
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

@media (max-width: 768px) {
    .order-details-page {
        padding: 1rem;
    }
    .order-details-actions {
        width: 100%;
        justify-content: flex-start;
    }
}
</style>

