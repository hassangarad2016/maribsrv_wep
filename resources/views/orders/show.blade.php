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
        ];];
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
            $coordinateDisplay ? 'ط§ظ„ط¥ط­ط¯ط§ط«ظٹط§طھ: ' . $coordinateDisplay : null,
            $mapUrl ? 'ط§ظ„ط®ط±ظٹط·ط©: ' . $mapUrl : null,
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

            <a href="{{ $reserveToggleUrl }}" class="btn btn-outline-secondary">
                <i class="bi {{ $showReserve ? 'إخفاء الحالات المحجوزة' : 'إظهار الحالات المحجوزة' }}"></i>
                {{ $showReserve ? 'إخفاء الحالات المحجوزة' : 'عرض الحالات المحجوزة' }}
            </a>

            <a href="{{ route('orders.edit', $order->id) }}" class="btn btn-primary">
                <i class="fa fa-edit"></i> تعديل الطلب
            </a>
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
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-pills" id="orderActionsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="orderActionsPaymentsTab" data-bs-toggle="tab" href="#orderActionsPayments" role="tab" aria-controls="orderActionsPayments" aria-selected="true">الدفع اليدوي</a>
                        </li>

                    </ul>
                    <div class="tab-content mt-3" id="orderActionsTabsContent">
                        <div class="tab-pane fade show active" id="orderActionsPayments" role="tabpanel" aria-labelledby="orderActionsPaymentsTab">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                @if($manualPaymentLocked)
                                    <span class="btn btn-outline-success disabled" aria-disabled="true" title="ظ„ط§ ظٹظ…ظƒظ† ط¥ط¶ط§ظپط© ط§ظ„ط·ظ„ط¨ ط¥ظ„ظ‰ ظ…ط¬ظ…ظˆط¹ط© ط£ط«ظ†ط§ط، ظ…ط±ط§ط¬ط¹ط© ط§ظ„ط¯ظپط¹" data-testid="add-to-payment-group-button">
                                        <i class="fa fa-layer-group"></i> إضافة إلى مجموعة الدفع</span>
                                @else
                                    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addOrderToGroupModal" data-testid="add-to-payment-group-button">
                                        <i class="fa fa-layer-group"></i> إضافة إلى مجموعة الدفع</button>
                                @endif

                                @if($manualPaymentLocked)
                                    <span class="btn btn-outline-warning disabled" aria-disabled="true" title="ظ„ط§ ظٹظ…ظƒظ† ط¥ط±ط³ط§ظ„ ط¥ط´ط¹ط§ط± ظپظˆط±ظٹ ط£ط«ظ†ط§ط، ظ…ط±ط§ط¬ط¹ط© ط§ظ„ط¯ظپط¹" data-testid="instant-notification-button">
                                        <i class="fa fa-bell"></i> إرسال إشعار فوري</span>
                                @else
                                    <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#instantNotificationModal" data-testid="instant-notification-button">
                                        <i class="fa fa-bell"></i> إرسال إشعار فوري</button>
                                @endif
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="row">
        <div class="col-12 col-xl-7 mb-4 mb-xl-0">
            <div class="card order-overview-card">
                <div class="card-header">
                    <h4 class="card-title">ظ…ط¹ظ„ظˆظ…ط§طھ ط§ظ„ط·ظ„ط¨</h4>
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
                            $statusBadgeTitle = trim('ظ…ط±ط­ظ„ط© ط§ط­طھظٹط§ط·ظٹط©' . ($statusBadgeTitle !== '' ? ' - ' . $statusBadgeTitle : ''));
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
                            : 'ط؛ظٹط± ظ…ط­ط¯ط¯';
                        $deliveryStatusLabel = $deliveryStatusValue
                            ? ($deliveryPaymentStatusLabels[$deliveryStatusValue] ?? \Illuminate\Support\Str::of($deliveryStatusValue)->replace('_', ' ')->headline())
                            : 'ط؛ظٹط± ظ…ط­ط¯ط¯';
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


                        $addressLabels = [
                            'label' => 'ط§ظ„ط¹ظ†ظˆط§ظ†',
                            'phone' => 'ط±ظ‚ظ… ط§ظ„ظ‡ط§طھظپ',
                            'street' => 'ط§ظ„ط´ط§ط±ط¹',
                            'building' => 'ط§ظ„ظ…ط¨ظ†ظ‰',
                            'apartment' => 'ط§ظ„ط´ظ‚ط©',
                            'city' => 'ط§ظ„ظ…ط¯ظٹظ†ط©',
                            'area' => 'ط§ظ„ظ…ظ†ط·ظ‚ط©',
                            'instructions' => 'ظ…ظ„ط§ط­ط¸ط§طھ ط§ظ„طھظˆطµظٹظ„',
                        ];

                        $cartMetrics = is_array(data_get($cartSnapshot, 'metrics')) ? $cartSnapshot['metrics'] : [];
                        $metricLabels = [
                            'cart_value' => 'ظ‚ظٹظ…ط© ط§ظ„ط³ظ„ط©',
                            'items_count' => 'ط¹ط¯ط¯ ط§ظ„ط¹ظ†ط§طµط±',
                            'weight_total' => 'ط§ظ„ظˆط²ظ† ط§ظ„ط¥ط¬ظ…ط§ظ„ظٹ (ظƒط¬ظ…)',
                        ];



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
                            null, '' => 'ط؛ظٹط± ظ…ط­ط¯ط¯',
                            'small' => 'طµط؛ظٹط±',
                            'medium' => 'ظ…طھظˆط³ط·',
                            'large' => 'ظƒط¨ظٹط±',
                            default => $deliverySizeValue,
                        };
                        $deliveryDistanceDisplay = $order->delivery_distance ? number_format($order->delivery_distance, 2) . ' ظƒظ…' : 'ط؛ظٹط± ظ…ط­ط¯ط¯ط©';
                        $deliveryPriceDisplay = $order->delivery_price ? number_format($order->delivery_price, 2) . ' ط±ظٹط§ظ„' : 'ط؛ظٹط± ظ…ط­ط¯ط¯';
                        $completedAtDisplay = $order->completed_at ? $order->completed_at->format('Y-m-d H:i') : 'ط؛ظٹط± ظ…ظƒطھظ…ظ„';

                        $latestManualPaymentRequest = $latestManualPaymentRequest ?? $order->manualPaymentRequests->first();
                        $manualPaymentStatus = $latestManualPaymentRequest?->status;
                        $manualPaymentStatusLabel = $manualPaymentStatus
                            ? ($manualPaymentStatusLabels[$manualPaymentStatus] ?? 'ط؛ظٹط± ظ…ط­ط¯ط¯')
                            : null;
                        $manualPaymentBadgeClass = $manualPaymentStatus
                            ? ($manualPaymentStatusBadgeClasses[$manualPaymentStatus] ?? 'bg-secondary')
                            : null;


                        $paymentStatusValue = $order->payment_status;
                        if ($manualPaymentStatusLabel !== null) {
                            $paymentStatusLabel = $manualPaymentStatusLabel;
                            $paymentStatusBadgeClass = $manualPaymentBadgeClass ?? 'bg-secondary';
                        } else {
                            $paymentStatusLabel = 'ط؛ظٹط± ظ…ط­ط¯ط¯';
                            $paymentStatusBadgeClass = 'bg-secondary';
                            if ($paymentStatusValue === 'pending') {
                                $paymentStatusLabel = 'ظ‚ظٹط¯ ط§ظ„ط§ظ†طھط¸ط§ط±';
                            } elseif ($paymentStatusValue === 'paid') {
                                $paymentStatusLabel = 'ظ…ط¯ظپظˆط¹';
                                $paymentStatusBadgeClass = 'bg-success';
                            } elseif ($paymentStatusValue === 'refunded') {
                                $paymentStatusLabel = 'ظ…ط³طھط±ط¬ط¹';
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
                        <section class="order-summary-block">
                            <h6 class="order-summary-heading">ط§ظ„ط¨ظٹط§ظ†ط§طھ ط§ظ„ط£ط³ط§ط³ظٹط©</h6>
                            <ul class="order-summary-list">
                                <li>
                                    <span class="order-summary-label">ط±ظ‚ظ… ط§ظ„ط·ظ„ط¨</span>
                                    <span class="order-summary-value">{{ $order->order_number }}</span>
                                </li>
                                <li>
                                    <span class="order-summary-label">طھط§ط±ظٹط® ط§ظ„ط·ظ„ط¨</span>
                                    <span class="order-summary-value">{{ $order->created_at->format('Y-m-d H:i') }}</span>
                                </li>
                                <li>
                                    <span class="order-summary-label">ط­ط§ظ„ط© ط§ظ„ط·ظ„ط¨</span>
                                    <span class="order-summary-value">
                                        <span class="badge d-inline-flex align-items-center gap-1" style="background-color: {{ $statusColor }}" @if($statusBadgeTitle !== '') title="{{ $statusBadgeTitle }}" @endif>
                                            @if($statusIconClass)
                                                <i class="{{ $statusIconClass }}"></i>
                                            @endif
                                            <span>{{ $statusLabel }}</span>
                                            @if($isReserveStatus)
                                                <span class="ms-1 small fw-semibold">ط§ط­طھظٹط§ط·ظٹ</span>

                                            @endif
                                        </span>
                                    </span>
                                </li>
                                <li>
                                    <span class="order-summary-label">ط§ظ„ظپط¦ط§طھ</span>
                                    <span class="order-summary-value">
                                        @forelse($categoryBadges as $categoryName)
                                            <span class="badge badge-info me-1 text-white">{{ $categoryName }}</span>
                                        @empty
                                            <span class="text-muted">ط؛ظٹط± ظ…طھظˆظپط±</span>
                                        @endforelse
                                    </span>
                                </li>
                                <li>
                                    <span class="order-summary-label">ط³ظٹط§ط³ط© ط§ظ„طھط³ط¹ظٹط±</span>
                                    <span class="order-summary-value">
                                        @if($policyCode)
                                            {{ $policyCode }}
                                            @if($policyId)
                                                <small class="text-muted">(#{{ $policyId }})</small>


                                            @endif
                                            @if(data_get($policyData, 'version'))
                                                <span class="badge badge-light">ط§ظ„ط¥طµط¯ط§ط± {{ data_get($policyData, 'version') }}</span>
                                            @endif
                                        @elseif($policyId)
                                            ط³ظٹط§ط³ط© #{{ $policyId }}
                                        @else
                                            <span class="text-muted">ط؛ظٹط± ظ…طھظˆظپط±</span>
                                        @endif
                                    </span>
                                </li>
                                <li>
                                    <span class="order-summary-label">طھط§ط±ظٹط® ط§ظ„ط¥ظƒظ…ط§ظ„</span>
                                    <span class="order-summary-value">{{ $completedAtDisplay }}</span>
                                </li>
                            </ul>
                        </section>

                        <section class="order-summary-block">
                            <h6 class="order-summary-heading">ظ…ط¹ظ„ظˆظ…ط§طھ ط§ظ„ط¯ظپط¹ ظˆط§ظ„طھظˆطµظٹظ„</h6>
                            <ul class="order-summary-list">
                                <li>
                                    <span class="order-summary-label">ط­ط§ظ„ط© ط§ظ„ط¯ظپط¹</span>
                                    <span class="order-summary-value">
                                        <span class="badge {{ $paymentStatusBadgeClass }}">{{ $paymentStatusLabel }}</span>



                                    </span>
                                </li>
                                <li>
                                    <span class="order-summary-label">ط·ط±ظٹظ‚ط© ط§ظ„ط¯ظپط¹</span>
                                    <span class="order-summary-value">{{ $order->resolved_payment_gateway_label ?? 'ط؛ظٹط± ظ…ط­ط¯ط¯' }}</span>
                                </li>
                                <li>
                                    <span class="order-summary-label">طھظˆظ‚ظٹطھ ط¯ظپط¹ ط§ظ„طھظˆطµظٹظ„</span>
                                    <span class="order-summary-value">{{ $timingLabel }}</span>
                                </li>
                                <li>
                                    <span class="order-summary-label">ط­ط§ظ„ط© ط¯ظپط¹ ط§ظ„طھظˆطµظٹظ„</span>
                                    <span class="order-summary-value">
                                        <span class="badge {{ $deliveryStatusValue ? $deliveryStatusClass : 'bg-secondary' }}">{{ $deliveryStatusLabel }}</span>
                                    </span>
                                </li>
                                <li>
                                    <span class="order-summary-label">ط§ظ„ظ…ط³طھط­ظ‚ ط¥ظ„ظƒطھط±ظˆظ†ظٹط§ظ‹</span>
                                    <span class="order-summary-value">
                                        @if(! is_null($onlinePayable))
                                            {{ number_format($onlinePayable, 2) }} ط±ظٹط§ظ„
                                        @else
                                            <span class="text-muted">ط؛ظٹط± ظ…طھظˆظپط±</span>
                                        @endif
                                    </span>
                                </li>
                                <li>
                                    <span class="order-summary-label">ط§ظ„ظ…ط³طھط­ظ‚ ط¹ظ†ط¯ ط§ظ„طھط³ظ„ظٹظ…</span>
                                    <span class="order-summary-value">
                                        @if(! is_null($codDue))
                                            {{ number_format($codDue, 2) }} ط±ظٹط§ظ„
                                        @else
                                            <span class="text-muted">ط؛ظٹط± ظ…طھظˆظپط±</span>
                                        @endif
                                    </span>
                                </li>
                                <li>
                                    <span class="order-summary-label">ط­ط¬ظ… ط§ظ„ط·ظ„ط¨</span>
                                    <span class="order-summary-value">{{ $deliverySizeLabel }}</span>
                                </li>
                                <li>
                                    <span class="order-summary-label">ظ…ط³ط§ظپط© ط§ظ„طھظˆطµظٹظ„</span>
                                    <span class="order-summary-value">{{ $deliveryDistanceDisplay }}</span>
                                </li>
                                <li>
                                    <span class="order-summary-label">ط³ط¹ط± ط§ظ„طھظˆطµظٹظ„</span>
                                    <span class="order-summary-value">{{ $deliveryPriceDisplay }}</span>
                                </li>
                            </ul>
                        </section>

                        <section class="order-summary-block order-summary-block--wide">
                            <h6 class="order-summary-heading">ط§ظ„ط£ط·ط±ط§ظپ</h6>
                            <div class="order-summary-parties">
                                <div class="order-party">
                                    <div class="order-party-title">ط§ظ„ط¹ظ…ظٹظ„</div>
                                    @if($order->user)
                                        <ul class="order-summary-list">
                                            <li>
                                                <span class="order-summary-label">ط§ظ„ط§ط³ظ…</span>
                                                <span class="order-summary-value">
                                                    <a href="{{ route('customer.show', $order->user_id) }}">{{ $order->user->name }}</a>
                                                </span>
                                            </li>
                                            <li>
                                                <span class="order-summary-label">ط±ظ‚ظ… ط§ظ„ظ‡ط§طھظپ</span>
                                                <span class="order-summary-value">
                                                    @if($order->user->mobile)
                                                        <a href="tel:{{ $order->user->mobile }}">{{ $order->user->mobile }}</a>
                                                    @else
                                                        <span class="text-muted">ط؛ظٹط± ظ…طھظˆظپط±</span>
                                                    @endif
                                                </span>
                                            </li>
                                            <li>
                                                <span class="order-summary-label">ط§ظ„ط¨ط±ظٹط¯ ط§ظ„ط¥ظ„ظƒطھط±ظˆظ†ظٹ</span>
                                                <span class="order-summary-value">
                                                    @if($order->user->email)
                                                        <a href="mailto:{{ $order->user->email }}">{{ $order->user->email }}</a>
                                                    @else
                                                        <span class="text-muted">ط؛ظٹط± ظ…طھظˆظپط±</span>
                                                    @endif
                                                </span>
                                            </li>
                                        </ul>
                                    @else
                                        <p class="text-muted mb-0">ظ…ط¹ظ„ظˆظ…ط§طھ ط§ظ„ط¹ظ…ظٹظ„ ط؛ظٹط± ظ…طھظˆظپط±ط©</p>
                                    @endif
                                </div>
                                <div class="order-party">
                                    <div class="order-party-title">ط§ظ„طھط§ط¬ط±</div>
                                    @if($order->seller)
                                        <ul class="order-summary-list">
                                            <li>
                                                <span class="order-summary-label">ط§ظ„ط§ط³ظ…</span>
                                                <span class="order-summary-value">
                                                    <a href="{{ route('customer.show', $order->seller_id) }}">{{ $order->seller->name }}</a>
                                                </span>
                                            </li>
                                            <li>
                                                <span class="order-summary-label">ط±ظ‚ظ… ط§ظ„ظ‡ط§طھظپ</span>
                                                <span class="order-summary-value">
                                                    @if($order->seller->mobile)
                                                        <a href="tel:{{ $order->seller->mobile }}">{{ $order->seller->mobile }}</a>
                                                    @else
                                                        <span class="text-muted">ط؛ظٹط± ظ…طھظˆظپط±</span>
                                                    @endif
                                                </span>
                                            </li>
                                            <li>
                                                <span class="order-summary-label">ط§ظ„ط¨ط±ظٹط¯ ط§ظ„ط¥ظ„ظƒطھط±ظˆظ†ظٹ</span>
                                                <span class="order-summary-value">
                                                    @if($order->seller->email)
                                                        <a href="mailto:{{ $order->seller->email }}">{{ $order->seller->email }}</a>
                                                    @else
                                                        <span class="text-muted">ط؛ظٹط± ظ…طھظˆظپط±</span>
                                                    @endif
                                                </span>
                                            </li>
                                            <li>
                                                <span class="order-summary-label">ط§ظ„ط¹ظ†ظˆط§ظ†</span>
                                                <span class="order-summary-value">{{ $order->seller->address ?? 'ط؛ظٹط± ظ…طھظˆظپط±' }}</span>
                                            </li>
                                        </ul>
                                    @else
                                        <p class="text-muted mb-0">ظ…ط¹ظ„ظˆظ…ط§طھ ط§ظ„طھط§ط¬ط± ط؛ظٹط± ظ…طھظˆظپط±ط©</p>
                                    @endif
                                </div>
                            </div>
                        </section>


                        <section class="order-summary-block order-summary-block--wide">
                            <h6 class="order-summary-heading">ط¹ظ†ظˆط§ظ† ط§ظ„ط´ط­ظ† ظˆط§ظ„طھطھط¨ط¹</h6>
                            <div class="order-address">
                                <div class="order-address-text">{{ $shippingAddressDisplay ?: 'ط؛ظٹط± ظ…طھظˆظپط±' }}</div>
                                <div class="order-address-actions">
                                    @php
                                        $availabilityBadgeClass = $hasCoordinates ? 'bg-success' : ($googleMapsUrl ? 'bg-info' : 'bg-secondary');
                                        $availabilityBadgeLabel = $hasCoordinates ? 'ط§ظ„ط¥ط­ط¯ط§ط«ظٹط§طھ ظ…طھظˆظپط±ط©' : ($googleMapsUrl ? 'ط±ط§ط¨ط· ظ…ظˆظ‚ط¹ ظ…طھظˆظپط±' : 'ط§ظ„ط¥ط­ط¯ط§ط«ظٹط§طھ ط؛ظٹط± ظ…طھظˆظپط±ط©');
                                    @endphp
                                    <span class="badge {{ $availabilityBadgeClass }}">{{ $availabilityBadgeLabel }}</span>
                                    @if($coordinateDisplay)
                                        <span class="text-muted small" dir="ltr">{{ $coordinateDisplay }}</span>
                                    @endif
                                    @if($hasCoordinates && $googleMapsUrl)
                                        <a href="{{ $googleMapsUrl }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-geo-alt"></i>
                                            ط®ط±ط§ط¦ط· ط¬ظˆط¬ظ„
                                        </a>
                                    @elseif(!$hasCoordinates && $googleMapsUrl)
                                        <a href="{{ $googleMapsUrl }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-geo-alt"></i>
                                            ظپطھط­ ط§ظ„ط®ط±ظٹط·ط©
                                        </a>
                                    @endif
                                    @if($hasCoordinates && $appleMapsUrl)
                                        <a href="{{ $appleMapsUrl }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-compass"></i>
                                            ط®ط±ط§ط¦ط· ط£ط¨ظ„
                                        </a>
                                    @endif
                                    @if($addressCopyText !== '')
                                        <button type="button" class="btn btn-outline-secondary btn-sm copy-address-btn" data-address-copy="{{ e($addressCopyText) }}">
                                            <i class="bi bi-clipboard"></i>
                                            ظ†ط³ط® ط§ظ„ط¹ظ†ظˆط§ظ†
                                        </button>
                                    @endif
                                </div>

                            </div>
            

                            @if(! $hasCoordinates && ! $googleMapsUrl)
                                <div class="text-muted small mt-2">ظ„ط§ طھطھظˆظپط± ط¨ظٹط§ظ†ط§طھ ظ…ظˆظ‚ط¹ ط¯ظ‚ظٹظ‚ط© ظ„ظ‡ط°ط§ ط§ظ„ط¹ظ†ظˆط§ظ†.</div>
                            @endif

                            <ul class="order-summary-list order-summary-list--compact mt-3">
                                <li>
                                    <span class="order-summary-label">ط±ط§ط¨ط· ط§ظ„طھطھط¨ط¹</span>
                                    <span class="order-summary-value">
                                        @if($trackingUrl)
                                            <a href="{{ $trackingUrl }}" target="_blank" rel="noopener">ظپطھط­ ط±ط§ط¨ط· ط§ظ„طھطھط¨ط¹</a>
                                        @else
                                            <span class="text-muted">ط؛ظٹط± ظ…طھظˆظپط±</span>
                                        @endif
                                    </span>
                                </li>
                                @if($trackingCarrier)
                                    <li>
                                        <span class="order-summary-label">ط´ط±ظƒط© ط§ظ„ط´ط­ظ†</span>
                                        <span class="order-summary-value">{{ $trackingCarrier }}</span>
                                    </li>
                                @endif
                                @if($trackingNumber)
                                    <li>
                                        <span class="order-summary-label">ط±ظ‚ظ… ط§ظ„طھطھط¨ط¹</span>
                                        <span class="order-summary-value"><code>{{ $trackingNumber }}</code></span>
                                    </li>
                                @endif
                            </ul>

                            @if($trackingProof !== [])
                                <div class="order-tracking-proof mt-3">
                                    <h6 class="order-summary-subheading">ط¥ط«ط¨ط§طھ ط§ظ„طھط³ظ„ظٹظ…</h6>
                                    <ul class="order-summary-list order-summary-list--compact mb-0">
                                        @if($trackingImagePath)
                                            <li>
                                                <span class="order-summary-label">ط§ظ„طµظˆط±ط©</span>
                                                <span class="order-summary-value">
                                                    @if($trackingImageUrl)
                                                        <a href="{{ $trackingImageUrl }}" target="_blank" rel="noopener">ط¹ط±ط¶</a>
                                                    @else
                                                        <code>{{ $trackingImagePath }}</code>
                                                    @endif
                                                </span>
                                            </li>
                                        @endif
                                        @if($trackingSignaturePath)
                                            <li>
                                                <span class="order-summary-label">ط§ظ„طھظˆظ‚ظٹط¹</span>
                                                <span class="order-summary-value">
                                                    @if($trackingSignatureUrl)
                                                        <a href="{{ $trackingSignatureUrl }}" target="_blank" rel="noopener">ط¹ط±ط¶</a>
                                                    @else
                                                        <code>{{ $trackingSignaturePath }}</code>
                                                    @endif
                                                </span>
                                            </li>
                                        @endif
                                        @if($trackingOtpCode)
                                            <li>
                                                <span class="order-summary-label">ط±ظ…ط² OTP</span>
                                                <span class="order-summary-value"><code>{{ $trackingOtpCode }}</code></span>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            @endif

                            @if($addressSnapshot !== [])
                                <div class="order-address-snapshot mt-3">
                                    <h6 class="order-summary-subheading">ط¨ظٹط§ظ†ط§طھ ط§ظ„ط¹ظ†ظˆط§ظ† ط§ظ„ظ…ط­ظپظˆط¸ط©</h6>
                                    <div class="order-address-grid">
                                        @foreach($addressSnapshot as $key => $value)
                                            <div class="order-address-field">
                                                <div class="order-summary-label">{{ $addressLabels[$key] ?? \Illuminate\Support\Str::of($key)->replace('_', ' ')->headline() }}</div>
                                                <div class="order-summary-value">
                                                    @if(is_array($value))
                                                        <code dir="ltr">{{ json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code>
                                                    @else
                                                        {{ ($value !== null && $value !== '') ? $value : 'â€”' }}
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </section>


                        <section class="order-summary-block">
                            <h6 class="order-summary-heading">ظ…ط¤ط´ط±ط§طھ ط§ظ„ط³ظ„ط©</h6>
                            @if($cartMetrics !== [])
                                <ul class="order-summary-list mb-0">
                                    @foreach($cartMetrics as $metricKey => $metricValue)


                                        <li>
                                            <span class="order-summary-label">{{ $metricLabels[$metricKey] ?? \Illuminate\Support\Str::of($metricKey)->replace('_', ' ')->headline() }}</span>
                                            <span class="order-summary-value">
                                                @if(is_array($metricValue))
                                                    <code dir="ltr">{{ json_encode($metricValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code>
                                                @else
                                                    {{ ($metricValue !== null && $metricValue !== '') ? $metricValue : 'â€”' }}


                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                            @else
                                <p class="text-muted mb-0">ظ„ط§ طھظˆط¬ط¯ ط¨ظٹط§ظ†ط§طھ ط¥ط­طµط§ط¦ظٹط© ظ…طھط§ط­ط©.</p>
                            @endif
                        </section>

                        @if($orderNotes !== '')
                            <section class="order-summary-block order-summary-block--wide">
                                <h6 class="order-summary-heading">ظ…ظ„ط§ط­ط¸ط§طھ ط§ظ„ط·ظ„ط¨</h6>
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
                                        <div class="order-item-actions">
                                            @if($item['product_url'])
                                                <a href="{{ $item['product_url'] }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                                                    <i class="bi bi-box-arrow-up-right"></i>
                                                    عرض المنتج
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

        <div class="col-12 col-xl-5"><div class="col-12 col-xl-5">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">ط³ط¬ظ„ ط§ظ„ط·ظ„ط¨</h4>
                </div>
                <div class="card-body">
                    @php

                        $normalizeStatus = static function ($status) {
                            if ($status === null) {
                                return null;
                            }

                            return \Illuminate\Support\Str::of($status)
                                ->trim()
                                ->lower()
                                ->replace('-', '_')
                                ->replace(' ', '_')
                                ->value();
                        };
                        
                        $normalizedHistoryStatuses = $statusHistoryEntries->pluck('status')
                            ->map($normalizeStatus)

                            ->filter()
                            ->unique()
                            ->values();

                        $normalizedTimestampStatuses = collect($order->status_timestamps ?? [])
                            ->keys()
                            ->map($normalizeStatus)
                            ->filter()
                            ->unique()
                            ->values();

                        $normalizedOrderStatus = $normalizeStatus($order->order_status);
                        $normalizedPaymentStatus = $normalizeStatus($order->payment_status);


                        $primaryStepCodes = collect($statusDisplayMap)
                            ->reject(static fn (array $config) => (bool) ($config['reserve'] ?? false))
                            ->keys()
                            ->values()
                            ->all();

                        $stepCodes = $primaryStepCodes;
                        $processingIndex = array_search('processing', $stepCodes, true);
                        $insertIndex = $processingIndex !== false ? $processingIndex + 1 : 1;
                        array_splice($stepCodes, $insertIndex, 0, ['paid']);
                        $stepCodes = array_values(array_unique($stepCodes));

                        $appendStep = static function (array $steps, string $code) {
                            if (! in_array($code, $steps, true)) {
                                $steps[] = $code;
                            }

                            return $steps;
                        };

                        foreach (['on_hold', 'returned', 'failed', 'canceled'] as $reserveCode) {
                            if ($normalizedHistoryStatuses->contains($reserveCode) || $normalizedOrderStatus === $reserveCode) {
                                $stepCodes = $appendStep($stepCodes, $reserveCode);
                            }
                        }


                        $successfulPaymentStatuses = ['paid', 'succeed', 'success', 'captured', 'completed'];
                        $partialPaymentStatuses = ['partial', 'payment_partial'];

                        $paidStageCompleted = ($normalizedPaymentStatus && in_array($normalizedPaymentStatus, $successfulPaymentStatuses, true))
                            || ($normalizedPaymentStatus && in_array($normalizedPaymentStatus, $partialPaymentStatuses, true));

                        $currentStepIndex = 0;
                        foreach ($stepCodes as $index => $code) {
                            $hasReachedStep = $normalizedHistoryStatuses->contains($code)
                                || $normalizedTimestampStatuses->contains($code)
                                || $normalizedOrderStatus === $code
                                || ($code === 'paid' && $paidStageCompleted);

                            if ($hasReachedStep) {
                                
                                $currentStepIndex = $index;
                            }
                        }
                    @endphp

                    <div class="d-flex justify-content-between align-items-center mb-4 mt-4 flex-wrap">
                        @foreach($stepCodes as $index => $code)
                            <div class="step text-center mb-3">
                                <div class="circle {{ $index <= $currentStepIndex ? 'active' : '' }}">{{ $index + 1 }}</div>
                                @php
                                    $stepIconClass = \App\Models\Order::statusIcon($code);
                                    $stepLabel = $statusLabels[$code] ?? \Illuminate\Support\Str::of($code)->replace('_', ' ')->headline();
                                @endphp
                                <div class="label d-flex align-items-center justify-content-center gap-1">
                                    @if($stepIconClass)
                                        <i class="{{ $stepIconClass }}"></i>
                                    @endif
                                    <span>{{ $stepLabel }}</span>
                                </div>
                            </div>


                            @if($index < count($stepCodes) - 1)
                                <div class="line"></div>
                            @endif
                        @endforeach
                    </div>


                    </div>
                    <hr>
                    <h5 class="mt-4 mb-3">ظ…ط¨ط§ظ„ط؛ ط§ظ„ط¯ظپط¹ ط­ط³ط¨ ط§ظ„طھظˆظ‚ظٹطھ</h5>
                    @php
                        $formatAmount = function ($value) {
                            return $value !== null ? number_format((float) $value, 2) . ' ط±ظٹط§ظ„' : 'â€”';
                        };

                        $onlineTotal = $deliverySummary['online_payable'] ?? data_get($paymentSummary, 'online_total');
                        $onlineGoodsPayable = $deliverySummary['online_goods_payable'] ?? data_get($paymentSummary, 'goods_online_payable');
                        $onlineDeliveryPayable = $deliverySummary['online_delivery_payable'] ?? data_get($paymentSummary, 'delivery_online_payable');
                        $onlineOutstanding = $deliverySummary['online_outstanding'] ?? data_get($paymentSummary, 'online_outstanding');
                        $codDueAmount = $deliverySummary['cod_due'] ?? data_get($paymentSummary, 'cod_due');
                        $codFeeAmount = $deliverySummary['cod_fee'] ?? data_get($paymentSummary, 'cod_fee');
                        $codOutstanding = $deliverySummary['cod_outstanding'] ?? data_get($paymentSummary, 'cod_outstanding');
                        $remainingBalance = data_get($paymentSummary, 'remaining_balance');
                    @endphp
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ط§ظ„ط¨ظ†ط¯</th>
                                    <th>ط§ظ„ظ…ط³طھط­ظ‚ ط§ظ„ط¢ظ†</th>
                                    <th>ط§ظ„ظ…ط³طھط­ظ‚ ط¹ظ†ط¯ ط§ظ„طھط³ظ„ظٹظ…</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>ط¥ط¬ظ…ط§ظ„ظٹ ط§ظ„ط¯ظپط¹ ط§ظ„ط¥ظ„ظƒطھط±ظˆظ†ظٹ</td>
                                    <td>{{ $formatAmount($onlineTotal) }}</td>
                                    <td>â€”</td>
                                </tr>
                                <tr>
                                    <td>ط§ظ„ط³ظ„ط¹ (ط¥ظ„ظƒطھط±ظˆظ†ظٹط§ظ‹)</td>
                                    <td>{{ $formatAmount($onlineGoodsPayable) }}</td>
                                    <td>â€”</td>
                                </tr>
                                <tr>
                                    <td>ط§ظ„طھظˆطµظٹظ„ (ط¥ظ„ظƒطھط±ظˆظ†ظٹط§ظ‹)</td>
                                    <td>{{ $formatAmount($onlineDeliveryPayable) }}</td>
                                    <td>â€”</td>
                                </tr>
                                <tr>
                                    <td>ط§ظ„ظ…ط¨ظ„ط؛ ط§ظ„ط¥ط¬ظ…ط§ظ„ظٹ ط¹ظ†ط¯ ط§ظ„طھط³ظ„ظٹظ…</td>
                                    <td>â€”</td>
                                    <td>{{ $formatAmount($codDueAmount) }}</td>
                                </tr>
                                <tr>
                                    <td>ط±ط³ظˆظ… ط§ظ„ط¯ظپط¹ ط¹ظ†ط¯ ط§ظ„طھط³ظ„ظٹظ…</td>
                                    <td>â€”</td>
                                    <td>{{ $formatAmount($codFeeAmount) }}</td>
                                </tr>
                                <tr>
                                    <td>ط§ظ„ط±طµظٹط¯ ط§ظ„ظ…طھط¨ظ‚ظٹ ط¥ظ„ظƒطھط±ظˆظ†ظٹط§ظ‹</td>
                                    <td>{{ $formatAmount($onlineOutstanding) }}</td>
                                    <td>â€”</td>
                                </tr>
                                <tr>
                                    <td>ط§ظ„ط±طµظٹط¯ ط§ظ„ظ…طھط¨ظ‚ظٹ ط¹ظ†ط¯ ط§ظ„طھط³ظ„ظٹظ…</td>
                                    <td>â€”</td>
                                    <td>{{ $formatAmount($codOutstanding) }}</td>
                                </tr>
                                <tr>
                                    <td>ط§ظ„ط±طµظٹط¯ ط§ظ„ط¥ط¬ظ…ط§ظ„ظٹ ط§ظ„ظ…طھط¨ظ‚ظٹ</td>
                                    <td colspan="2">{{ $formatAmount($remainingBalance) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <hr>
                    <h5 class="mt-4 mb-3">ط³ط¬ظ„ ط§ظ„ط­ط§ظ„ط©</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>ط§ظ„ط­ط§ظ„ط©</th>
                                    <th>ط§ظ„ظ…ط³طھط®ط¯ظ…</th>
                                    <th>ط§ظ„طھط§ط±ظٹط®</th>
                                    <th>ط§ظ„طھظپط§طµظٹظ„</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($statusHistoryEntries as $index => $entry)
                                    @php
                                        $statusValue = $entry['status'] ?? null;
                                        $statusLabelEntry = $statusValue
                                            ? ($statusLabels[$statusValue] ?? \Illuminate\Support\Str::of($statusValue)->replace('_', ' ')->headline())
                                            : 'ط؛ظٹط± ظ…ط­ط¯ط¯';
                                        $userId = $entry['user_id'] ?? null;
                                        $userName = $userId !== null
                                            ? ($statusHistoryUsers[$userId]->name ?? ('#' . $userId))
                                            : 'ط؛ظٹط± ظ…ط¹ط±ظˆظپ';
                                        $recordedAt = $entry['recorded_at'] ?? null;
                                        $recordedAtFormatted = $recordedAt ? optional(\Illuminate\Support\Carbon::make($recordedAt))->format('Y-m-d H:i') : null;
                                        $displayMessage = $entry['display'] ?? null;
                                        $manualComment = $entry['comment'] ?? null;
                                        $iconClass = $entry['icon'] ?? null;
                                 

                                 
                                        @endphp
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $statusLabelEntry }}</td>
                                        <td>{{ $userName }}</td>
                                        <td>{{ $recordedAtFormatted ?? 'ط؛ظٹط± ظ…ط­ط¯ط¯' }}</td>
                                        <td>
                                            <div class="d-flex align-items-start">
                                                @if($iconClass)
                                                    <span class="text-secondary me-2"><i class="{{ $iconClass }}"></i></span>
                                                @endif
                                                <div>
                                                    {{ $displayMessage ?? $manualComment ?? 'â€”' }}
                                                    @if($manualComment && $displayMessage && $manualComment !== $displayMessage)
                                                        <div class="small text-muted mt-1">{{ $manualComment }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">ظ„ط§ طھظˆط¬ط¯ ط³ط¬ظ„ط§طھ ط­ط§ظ„ط© ظ…طھظˆظپط±ط©.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>


                </div>
            </div>
        </div>
    </div>



    <div class="row mt-4">
        <!-- طھط­ط¯ظٹط« ط­ط§ظ„ط© ط§ظ„ط·ظ„ط¨ -->
        <div class="col-12">
>
                <div class="card-header">
                    <h4 class="card-title">طھط­ط¯ظٹط« ط­ط§ظ„ط© ط§ظ„ط·ظ„ط¨</h4>
                </div>
                <div class="card-body">
                    @php
                        $paymentStatusLocked = true;
                        $orderStatusLocked = isset($pendingManualPaymentRequest) && $pendingManualPaymentRequest;
                        $paymentStatusLabel = $paymentStatusOptions[$order->payment_status] ?? $order->payment_status;
                        
                        $statusLabel = \App\Models\Order::statusLabel($order->order_status);
                        $canChangeOrderStatus = $order->hasSuccessfulPayment() && ! $orderStatusLocked;
                        $orderStatusLockMessage = null;

                        if (! $order->hasSuccessfulPayment()) {
                            $orderStatusLockMessage = 'ظ„ط§ ظٹظ…ظƒظ† طھط¹ط¯ظٹظ„ ط­ط§ظ„ط© ط§ظ„ط·ظ„ط¨ ظ‚ط¨ظ„ طھط£ظƒظٹط¯ ط§ظ„ط¯ظپط¹ ط¨ظ†ط¬ط§ط­.';
                        } elseif ($orderStatusLocked) {
                            $orderStatusLockMessage = 'ظ„ط§ ظٹظ…ظƒظ† طھط¹ط¯ظٹظ„ ط­ط§ظ„ط© ط§ظ„ط·ظ„ط¨ ط­طھظ‰ ظٹطھظ… ط§ط¹طھظ…ط§ط¯ ط§ظ„ط¯ظپط¹ط© ظ…ظ† ط®ظ„ط§ظ„ ظپط±ظٹظ‚ ط§ظ„ظ…ط¯ظپظˆط¹ط§طھ.';
                        }

                    @endphp
                    @if($canChangeOrderStatus)

                    <form action="{{ route('orders.update', $order->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="form-group">
                            <label for="order_status">ط­ط§ظ„ط© ط§ظ„ط·ظ„ط¨</label>
                            <select class="form-control" id="order_status" name="order_status" required>
                                @foreach($orderStatuses as $status)
                                    <option value="{{ $status->code }}" {{ $order->order_status == $status->code ? 'selected' : '' }}
                                        style="color: {{ $status->color }}">
                                        {{ $status->name }}
                                    </option>
                                @endforeach
                            </select>


                        </div>
                        <div class="form-group">
                            <label class="form-label">ط­ط§ظ„ط© ط§ظ„ط¯ظپط¹</label>
                            <div class="form-control-plaintext border rounded bg-light px-3 py-2">
                                {{ $paymentStatusLabel ?? 'â€”' }}
                            </div>
                            <small class="text-muted d-block mt-2">ظٹطھظ… طھط­ط¯ظٹط« ط­ط§ظ„ط© ط§ظ„ط¯ظپط¹ ط­طµط±ط§ظ‹ ظ…ظ† ط®ظ„ط§ظ„ ظˆط§ط¬ظ‡ط© ط·ظ„ط¨ط§طھ ط§ظ„ط¯ظپط¹.</small>
                                
                        </div>
                        <div class="form-group">
                            <label for="comment">ظ…ظ„ط§ط­ط¸ط§طھ ط§ظ„طھط­ط¯ظٹط«</label>
                            <textarea class="form-control" id="comment" name="comment" rows="3"></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="notify_customer" name="notify_customer" value="1">
                            <label class="form-check-label" for="notify_customer">
                                ط¥ط´ط¹ط§ط± ط§ظ„ط¹ظ…ظٹظ„ ط¨ط§ظ„طھط­ط¯ظٹط«
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary">طھط­ط¯ظٹط« ط§ظ„ط­ط§ظ„ط©</button>
                    </form>

                    @else
                        <div class="form-group">
                            <label class="form-label">ط­ط§ظ„ط© ط§ظ„ط·ظ„ط¨</label>
                            <div class="form-control-plaintext border rounded bg-light px-3 py-2">
                                {{ $statusLabel ?? 'â€”' }}
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ط­ط§ظ„ط© ط§ظ„ط¯ظپط¹</label>
                            <div class="form-control-plaintext border rounded bg-light px-3 py-2">
                                {{ $paymentStatusLabel ?? 'â€”' }}
                            </div>
                            <small class="text-muted d-block mt-2">ظٹطھظ… طھط­ط¯ظٹط« ط­ط§ظ„ط© ط§ظ„ط¯ظپط¹ ط­طµط±ط§ظ‹ ظ…ظ† ط®ظ„ط§ظ„ ظˆط§ط¬ظ‡ط© ط·ظ„ط¨ط§طھ ط§ظ„ط¯ظپط¹.</small>
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
                    <h5 class="modal-title" id="addOrderToGroupModalLabel">ط¥ط¶ط§ظپط© ط§ظ„ط·ظ„ط¨ ط¥ظ„ظ‰ ظ…ط¬ظ…ظˆط¹ط©</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <h6 class="mb-3">ط§ظ„ظ…ط¬ظ…ظˆط¹ط§طھ ط§ظ„ظ…طھط§ط­ط©</h6>
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
                                            <span><i class="fa fa-list-ol"></i> {{ number_format($group->orders_count) }} ط·ظ„ط¨</span>
                                            @if ($group->created_at)
                                                <span><i class="fa fa-calendar"></i> {{ $group->created_at->format('Y-m-d') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-outline-success">
                                        <i class="fa fa-plus"></i> ط¥ط¶ط§ظپط© ط¥ظ„ظ‰ ظ‡ط°ظ‡ ط§ظ„ظ…ط¬ظ…ظˆط¹ط©
                                    </button>
                                </div>
                            </form>
                        @empty
                            <p class="text-muted mb-0">ظ„ط§ طھظˆط¬ط¯ ظ…ط¬ظ…ظˆط¹ط§طھ ظ…طھط§ط­ط© ط­ط§ظ„ظٹط§ظ‹ ظ„ظ‡ط°ط§ ط§ظ„ط·ظ„ط¨. ظٹظ…ظƒظ†ظƒ ط¥ظ†ط´ط§ط، ظ…ط¬ظ…ظˆط¹ط© ط¬ط¯ظٹط¯ط© ط¨ط§ط³طھط®ط¯ط§ظ… ط§ظ„ظ†ظ…ظˆط°ط¬ ط£ط¯ظ†ط§ظ‡.</p>
                        @endforelse
                    </div>
                    <div>
                        <h6 class="mb-3">ط¥ظ†ط´ط§ط، ظ…ط¬ظ…ظˆط¹ط© ط¬ط¯ظٹط¯ط©</h6>
                        <form action="{{ route('orders.payment-groups.store', $order) }}" method="POST" data-testid="create-payment-group-form">
                            @csrf
                            <div class="form-group">
                                <label for="payment_group_name">ط§ط³ظ… ط§ظ„ظ…ط¬ظ…ظˆط¹ط©</label>
                                <input type="text" id="payment_group_name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" maxlength="190" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group mb-0">
                                <label for="payment_group_note">ظ…ظ„ط§ط­ط¸ط© (ط§ط®طھظٹط§ط±ظٹ)</label>
                                <textarea id="payment_group_note" name="note" rows="3" class="form-control @error('note') is-invalid @enderror" maxlength="1000">{{ old('note') }}</textarea>
                                @error('note')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mt-3 d-flex justify-content-end align-items-center gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-layer-group"></i> إضافة إلى مجموعة الدفع</button>
                            </div>
                            <p class="small text-muted mb-0 mt-2">ط³ظٹطھظ… ط¥ط¶ط§ظپط© ظ‡ط°ط§ ط§ظ„ط·ظ„ط¨ طھظ„ظ‚ط§ط¦ظٹط§ظ‹ ط¥ظ„ظ‰ ط§ظ„ظ…ط¬ظ…ظˆط¹ط© ط§ظ„ط¬ط¯ظٹط¯ط© ط¨ط¹ط¯ ط¥ظ†ط´ط§ط¦ظ‡ط§.</p>
                        </form>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ط¥ط؛ظ„ط§ظ‚</button>
                </div>
            
            </div>
        </div>
    </div>

    <!-- Instant notification modal -->
    <div class="modal fade" id="instantNotificationModal" tabindex="-1" role="dialog" aria-labelledby="instantNotificationModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="instantNotificationModalLabel">ط¥ط±ط³ط§ظ„ ط¥ط´ط¹ط§ط± ظپظˆط±ظٹ ظ„ظ„ط¹ظ…ظٹظ„</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('orders.notifications.send', $order) }}" method="POST" data-testid="instant-notification-form">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="instant_notification_title" class="form-label">ط¹ظ†ظˆط§ظ† ط§ظ„ط¥ط´ط¹ط§ط± (ط§ط®طھظٹط§ط±ظٹ)</label>
                            <input type="text" class="form-control" id="instant_notification_title" name="title" maxlength="190" placeholder="ط¹ظ†ظˆط§ظ† ظ…ظˆط¬ط² ظ„ظ„ط¥ط´ط¹ط§ط±">
                        </div>
                        <div class="form-group mb-0">
                            <label for="instant_notification_message" class="form-label">ظ†طµ ط§ظ„ط¥ط´ط¹ط§ط±</label>
                            <textarea class="form-control" id="instant_notification_message" name="message" rows="4" required placeholder="ط§ظƒطھط¨ ط§ظ„ط±ط³ط§ظ„ط© ط§ظ„طھظٹ ط³ظٹطھظ… ط¥ط±ط³ط§ظ„ظ‡ط§ ظ„ظ„ط¹ظ…ظٹظ„"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ط¥ظ„ط؛ط§ط،</button>
                        <button type="submit" class="btn btn-warning">ط¥ط±ط³ط§ظ„ ط§ظ„ط¥ط´ط¹ط§ط±</button>
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
                    <h5 class="modal-title" id="deleteModalLabel">طھط£ظƒظٹط¯ ط§ظ„ط­ط°ظپ</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    ظ‡ظ„ ط£ظ†طھ ظ…طھط£ظƒط¯ ظ…ظ† ط­ط°ظپ ط§ظ„ط·ظ„ط¨ ط±ظ‚ظ… <strong>{{ $order->order_number }}</strong>طں
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ط¥ظ„ط؛ط§ط،</button>
                    <form action="{{ route('orders.destroy', $order->id) }}" method="POST" style="display: inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">ط­ط°ظپ</button>
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
                const successMessage = 'طھظ… ظ†ط³ط® ط§ظ„ط¹ظ†ظˆط§ظ† ط¨ظ†ط¬ط§ط­';
                const errorMessage = 'طھط¹ط°ط± ظ†ط³ط® ط§ظ„ط¹ظ†ظˆط§ظ†طŒ ظٹط±ط¬ظ‰ ط§ظ„ظ†ط³ط® ظٹط¯ظˆظٹظ‹ط§';

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