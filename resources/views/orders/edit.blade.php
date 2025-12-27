@extends('layouts.main')

@php
    use App\Models\ManualPaymentRequest;
    use App\Models\Order;
@endphp

@section('title', 'تعديل الطلب #' . $order->order_number)

@section('content')
<div class="container-fluid order-details-page">
    @php
        $currencyCode = strtoupper((string) ($order->currency ?? config('app.currency', 'YER')));
        $statusDisplayMap = Order::statusDisplayMap();
        $statusCollection = $orderStatuses instanceof \Illuminate\Support\Collection
            ? $orderStatuses
            : collect($orderStatuses ?? []);
        $matchedStatus = $statusCollection->firstWhere('code', $order->order_status);
        $statusDisplayEntry = $statusDisplayMap[$order->order_status] ?? null;
        $statusLabel = Order::statusLabel($order->order_status);
        if ($statusLabel === '') {
            $statusLabel = optional($matchedStatus)->name
                ?? (is_array($statusDisplayEntry) ? ($statusDisplayEntry['label'] ?? null) : null)
                ?? \Illuminate\Support\Str::of($order->order_status)->replace('_', ' ')->headline();
        }
        $statusColor = optional($matchedStatus)->color ?: '#6c757d';
        $statusIcon = Order::statusIcon($order->order_status);
        if (! $statusIcon && is_array($statusDisplayEntry)) {
            $statusIcon = $statusDisplayEntry['icon'] ?? null;
        }

        $manualPaymentStatusLabels = [
            ManualPaymentRequest::STATUS_PENDING => 'قيد الانتظار',
            ManualPaymentRequest::STATUS_UNDER_REVIEW => 'قيد المراجعة',
            ManualPaymentRequest::STATUS_APPROVED => 'تمت الموافقة',
            ManualPaymentRequest::STATUS_REJECTED => 'مرفوض',
        ];
        $manualPaymentStatusBadgeClasses = [
            ManualPaymentRequest::STATUS_PENDING => 'bg-warning text-dark',
            ManualPaymentRequest::STATUS_UNDER_REVIEW => 'bg-warning text-dark',
            ManualPaymentRequest::STATUS_APPROVED => 'bg-success',
            ManualPaymentRequest::STATUS_REJECTED => 'bg-danger',
        ];

        $latestManualPaymentRequest = $latestManualPaymentRequest ?? ($order->manualPaymentRequests->first() ?? null);
        $manualPaymentStatus = $latestManualPaymentRequest?->status;
        $manualPaymentLocked = $pendingManualPaymentRequest !== null;

        $paymentStatusLabels = Order::paymentStatusLabels();
        if ($manualPaymentStatus !== null) {
            $paymentStatusLabel = $manualPaymentStatusLabels[$manualPaymentStatus] ?? 'غير معروف';
            $paymentStatusClass = $manualPaymentStatusBadgeClasses[$manualPaymentStatus] ?? 'bg-secondary';
        } else {
            $paymentStatusLabel = $paymentStatusLabels[$order->payment_status]
                ?? ($order->payment_status ?: 'غير معروف');
            $paymentStatusClass = match ($order->payment_status) {
                'paid', 'success', 'succeed', 'completed', 'captured' => 'bg-success',
                'pending' => 'bg-warning text-dark',
                'partial', 'payment_partial' => 'bg-primary',
                'refunded' => 'bg-info',
                'failed', 'canceled', 'cancelled' => 'bg-danger',
                default => 'bg-secondary',
            };
        }

        $paymentMethodLabel = $order->resolved_payment_gateway_label
            ?? $order->payment_method
            ?? 'غير محدد';

        $deliverySummary = $order->delivery_payment_summary ?? [];
        $paymentSummary = $order->payment_summary ?? [];
        $timingValue = $order->delivery_payment_timing ?? ($deliverySummary['timing'] ?? null);
        $deliveryStatusValue = $order->delivery_payment_status ?? ($deliverySummary['status'] ?? null);
        $timingLabel = $timingValue
            ? ($deliveryPaymentTimingLabels[$timingValue] ?? $timingValue)
            : 'غير محدد';
        $deliveryStatusLabel = $deliveryStatusValue
            ? ($deliveryPaymentStatusLabels[$deliveryStatusValue] ?? $deliveryStatusValue)
            : 'غير محدد';

        $orderNotes = trim((string) ($order->notes ?? ''));
        $canUpdateStatus = $order->hasSuccessfulPayment() && ! $manualPaymentLocked;
        $orderStatusLockMessage = null;
        if ($manualPaymentLocked) {
            $orderStatusLockMessage = 'يوجد طلب دفع يدوي قيد المراجعة ويجب إنهاؤه قبل تغيير حالة الطلب.';
        } elseif (! $order->hasSuccessfulPayment()) {
            $orderStatusLockMessage = 'لا يمكن تغيير حالة الطلب قبل اكتمال عملية الدفع.';
        }

        $cartSnapshot = is_array($order->cart_snapshot) ? $order->cart_snapshot : [];
        $cartItemsSnapshot = collect(data_get($cartSnapshot, 'items', []))
            ->filter(static fn ($item) => is_array($item))
            ->mapWithKeys(static function (array $item): array {
                $itemId = data_get($item, 'item_id');
                $variantId = data_get($item, 'variant_id');
                $key = sprintf('%s:%s', $itemId ?? 'null', $variantId ?? 'null');
                return [$key => $item];
            });

        $normalizeImageUrl = static function ($value): ?string {
            if ($value === null) {
                return null;
            }
            if (is_array($value)) {
                $value = reset($value);
            }
            if (! is_scalar($value)) {
                return null;
            }
            $value = trim((string) $value);
            if ($value === '') {
                return null;
            }
            if (\Illuminate\Support\Str::startsWith($value, ['http://', 'https://'])) {
                return $value;
            }
            if (\Illuminate\Support\Str::startsWith($value, '//')) {
                return 'https:' . $value;
            }
            return asset(ltrim($value, '/'));
        };

        $normalizeExternalUrl = static function ($value): ?string {
            if ($value === null) {
                return null;
            }
            if (! is_scalar($value)) {
                return null;
            }
            $value = trim((string) $value);
            if ($value === '') {
                return null;
            }
            if (\Illuminate\Support\Str::startsWith($value, ['http://', 'https://'])) {
                return $value;
            }
            if (\Illuminate\Support\Str::startsWith($value, '//')) {
                return 'https:' . $value;
            }
            return \Illuminate\Support\Str::contains($value, '.') ? 'https://' . ltrim($value, '/') : null;
        };

        $orderItemsDisplayData = $order->items->map(function ($orderItem) use ($cartItemsSnapshot, $normalizeImageUrl, $normalizeExternalUrl) {
            $itemSnapshot = is_array($orderItem->item_snapshot) ? $orderItem->item_snapshot : [];
            $pricingSnapshot = is_array($orderItem->pricing_snapshot) ? $orderItem->pricing_snapshot : [];
            $snapshotKey = sprintf('%s:%s', $orderItem->item_id ?? 'null', $orderItem->variant_id ?? 'null');
            $cartSnapshotItem = $cartItemsSnapshot->get($snapshotKey, []);
            if (! is_array($cartSnapshotItem)) {
                $cartSnapshotItem = [];
            }

            $options = $orderItem->options ?? $orderItem->attributes ?? data_get($itemSnapshot, 'attributes', []);
            if (is_string($options)) {
                $decoded = json_decode($options, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $options = $decoded;
                }
            }
            $options = is_array($options) ? $options : [];

            $optionsDisplay = collect($options)
                ->map(static function ($value, $key) {
                    $normalizedKey = \Illuminate\Support\Str::of((string) $key)->lower()->replace(['_', '-', ' '], '');
                    $normalizedKeyValue = (string) $normalizedKey;
                    $isColorKey = \Illuminate\Support\Str::contains($normalizedKeyValue, ['color', 'colour', 'لون']);
                    $isSizeKey = \Illuminate\Support\Str::contains($normalizedKeyValue, ['size', 'مقاس']);
                    $isAttrKey = \Illuminate\Support\Str::startsWith($normalizedKeyValue, ['attr', 'attribute']);

                    if (is_array($value)) {
                        $value = collect($value)
                            ->map(static fn ($item) => is_scalar($item) ? trim((string) $item) : null)
                            ->filter()
                            ->implode(', ');
                    } elseif (is_bool($value)) {
                        $value = $value ? 'نعم' : 'لا';
                    }

                    if (is_scalar($value)) {
                        $value = trim((string) $value);
                    } else {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }

                    if ($value === null || $value === '') {
                        return null;
                    }

                    $colorValue = null;
                    if (is_string($value)) {
                        $colorCandidate = trim($value);
                        if (preg_match('/^#?[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $colorCandidate) === 1) {
                            $colorValue = '#' . ltrim($colorCandidate, '#');
                        }
                    }

                    if (! $isColorKey && $colorValue) {
                        $isColorKey = true;
                    }

                    $label = \Illuminate\Support\Str::of((string) $key)
                        ->replace('_', ' ')
                        ->squish()
                        ->title();

                    if ($isColorKey) {
                        $label = 'اللون';
                    } elseif ($isSizeKey || $isAttrKey) {
                        $label = 'المقاس';
                        $isSizeKey = true;
                    }

                    $displayValue = $value;
                    if ($isColorKey && $colorValue) {
                        $displayValue = null;
                    }

                    return [
                        'label' => (string) $label,
                        'value' => $displayValue,
                        'raw_value' => $value,
                        'is_color' => $isColorKey,
                        'color_value' => $colorValue,
                        'is_size' => $isSizeKey,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            $advertiserSource = collect([
                data_get($cartSnapshotItem, 'stock_snapshot.department_advertiser'),
                data_get($itemSnapshot, 'stock_snapshot.department_advertiser'),
                data_get($itemSnapshot, 'department_advertiser'),
                data_get($pricingSnapshot, 'department_advertiser'),
                data_get($pricingSnapshot, 'advertisement'),
            ])->first(static fn ($data) => is_array($data) && collect($data)->filter(fn ($value) => filled($value))->isNotEmpty());

            $advertiserFields = [];

            if (is_array($advertiserSource)) {
                $labelOverrides = [
                    'name' => 'الاسم',
                    'contact_number' => 'رقم الهاتف',
                    'message_number' => 'رقم الواتساب',
                    'location' => 'العنوان',
                    'notes' => 'ملاحظات',
                    'reference' => 'مرجع',
                    'id' => 'معرف الإعلان',
                ];

                foreach ($advertiserSource as $field => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }

                    if (is_array($value)) {
                        $value = collect($value)
                            ->map(static fn ($item) => is_scalar($item) ? trim((string) $item) : null)
                            ->filter()
                            ->implode(', ');
                    } elseif (is_bool($value)) {
                        $value = $value ? 'نعم' : 'لا';
                    }

                    if (is_scalar($value)) {
                        $value = trim((string) $value);
                    } else {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }

                    if ($value === '') {
                        continue;
                    }

                    $label = $labelOverrides[$field] ?? \Illuminate\Support\Str::of((string) $field)
                        ->replace('_', ' ')
                        ->squish()
                        ->title();

                    $advertiserFields[] = [
                        'label' => (string) $label,
                        'value' => $value,
                    ];
                }
            }

            $thumbnailCandidates = [
                $orderItem->item?->image,
                data_get($itemSnapshot, 'image'),
                data_get($itemSnapshot, 'thumbnail'),
                data_get($itemSnapshot, 'stock_snapshot.image'),
                data_get($itemSnapshot, 'stock_snapshot.thumbnail'),
                data_get($itemSnapshot, 'stock_snapshot.images.0'),
                data_get($cartSnapshotItem, 'stock_snapshot.image'),
                data_get($cartSnapshotItem, 'stock_snapshot.images.0'),
                data_get($cartSnapshotItem, 'image'),
            ];

            $thumbnailUrl = collect($thumbnailCandidates)
                ->map($normalizeImageUrl)
                ->filter()
                ->first();

            if (! $thumbnailUrl) {
                $thumbnailUrl = asset('assets/images/no_image_available.png');
            }

            $productUrl = null;
            if ($orderItem->item_id) {
                if (\Illuminate\Support\Facades\Route::has('item.details')) {
                    $productUrl = route('item.details', ['item' => $orderItem->item_id]);
                } else {
                    $productUrl = url(sprintf('item/%s/details', $orderItem->item_id));
                }
            }

            $reviewUrl = data_get($itemSnapshot, 'review_link')
                ?? data_get($itemSnapshot, 'review_url')
                ?? data_get($cartSnapshotItem, 'review_link')
                ?? data_get($cartSnapshotItem, 'review_url')
                ?? $orderItem->item?->review_link;
            $reviewUrl = $normalizeExternalUrl($reviewUrl);

            $variantLabel = data_get($itemSnapshot, 'variant_name')
                ?? data_get($cartSnapshotItem, 'variant_name');

            return [
                'id' => $orderItem->getKey(),
                'item_id' => $orderItem->item_id,
                'variant_id' => $orderItem->variant_id,
                'name' => $orderItem->item_name ?? $orderItem->item?->name,
                'price' => (float) $orderItem->price,
                'quantity' => (float) $orderItem->quantity,
                'subtotal' => (float) $orderItem->subtotal,
                'options' => $optionsDisplay,
                'has_options' => ! empty($optionsDisplay),
                'advertiser' => $advertiserFields,
                'has_advertiser' => ! empty($advertiserFields),
                'thumbnail_url' => $thumbnailUrl,
                'product_url' => $productUrl,
                'review_url' => $reviewUrl,
                'variant_label' => $variantLabel,
                'currency' => $orderItem->currency ?? data_get($pricingSnapshot, 'currency') ?? data_get($cartSnapshotItem, 'currency'),
            ];
        })->values();
    @endphp

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="order-details-hero">
        <div class="order-details-info">
            <div class="order-details-kicker">طلب رقم #{{ $order->order_number }}</div>
            <h2 class="order-details-title">تعديل الطلب</h2>
            <div class="order-details-meta">
                <span class="badge d-inline-flex align-items-center gap-1" style="background-color: {{ $statusColor }}">
                    @if($statusIcon)
                        <i class="{{ $statusIcon }}"></i>
                    @endif
                    {{ $statusLabel }}
                </span>
                <span class="badge {{ $paymentStatusClass }}">{{ $paymentStatusLabel }}</span>
                <span class="text-muted">
                    <i class="bi bi-calendar3"></i> {{ $order->created_at->format('Y-m-d H:i') }}
                </span>
            </div>
        </div>
        <div class="order-details-actions">
            <a href="{{ route('orders.show', $order->id) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-right"></i> العودة للتفاصيل
            </a>
            <button type="submit" form="order-update-form" class="btn btn-primary">
                <i class="fa fa-save"></i> حفظ التعديلات
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
                <div class="order-metric-value">{{ $order->items->count() }}</div>
            </div>
            <div class="order-metric-icon">
                <i class="bi bi-bag-check"></i>
            </div>
        </div>
        <div class="order-metric-card">
            <div>
                <div class="order-metric-label">العميل</div>
                <div class="order-metric-value">{{ optional($order->user)->name ?? '-' }}</div>
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
                <div class="order-metric-value">{{ optional($order->seller)->name ?? '-' }}</div>
                @if(optional($order->seller)->mobile)
                    <div class="order-metric-sub">{{ $order->seller->mobile }}</div>
                @endif
            </div>
            <div class="order-metric-icon">
                <i class="bi bi-shop"></i>
            </div>
        </div>
    </div>

    <form action="{{ route('orders.update', $order->id) }}" method="POST" id="order-update-form" class="order-edit-form">
        @csrf
        @method('PUT')

        <div class="order-summary-grid">
            <section class="order-summary-block">
                <h6 class="order-summary-heading">البيانات الأساسية</h6>
                <ul class="order-summary-list">
                    <li>
                        <span class="order-summary-label">رقم الطلب</span>
                        <span class="order-summary-value">#{{ $order->order_number }}</span>
                    </li>
                    <li>
                        <span class="order-summary-label">تاريخ الطلب</span>
                        <span class="order-summary-value">{{ $order->created_at->format('Y-m-d H:i') }}</span>
                    </li>
                    <li>
                        <span class="order-summary-label">حالة الطلب</span>
                        <span class="order-summary-value">
                            <span class="badge d-inline-flex align-items-center gap-1" style="background-color: {{ $statusColor }}">
                                @if($statusIcon)
                                    <i class="{{ $statusIcon }}"></i>
                                @endif
                                {{ $statusLabel }}
                            </span>
                        </span>
                    </li>
                    <li>
                        <span class="order-summary-label">حالة الدفع</span>
                        <span class="order-summary-value">
                            <span class="badge {{ $paymentStatusClass }}">{{ $paymentStatusLabel }}</span>
                        </span>
                    </li>
                    <li>
                        <span class="order-summary-label">طريقة الدفع</span>
                        <span class="order-summary-value">{{ $paymentMethodLabel }}</span>
                    </li>
                    @if($order->payment_reference)
                        <li>
                            <span class="order-summary-label">مرجع الدفع</span>
                            <span class="order-summary-value">{{ $order->payment_reference }}</span>
                        </li>
                    @endif
                </ul>
            </section>

            <section class="order-summary-block">
                <h6 class="order-summary-heading">معلومات الدفع والتوصيل</h6>
                <ul class="order-summary-list">
                    <li>
                        <span class="order-summary-label">توقيت دفع التوصيل</span>
                        <span class="order-summary-value">{{ $timingLabel }}</span>
                    </li>
                    <li>
                        <span class="order-summary-label">حالة دفع التوصيل</span>
                        <span class="order-summary-value">{{ $deliveryStatusLabel }}</span>
                    </li>
                    <li>
                        <span class="order-summary-label">مسافة التوصيل</span>
                        <span class="order-summary-value">{{ $order->delivery_distance !== null ? number_format($order->delivery_distance, 2) . ' كم' : 'غير محدد' }}</span>
                    </li>
                    <li>
                        <span class="order-summary-label">حجم الطلب</span>
                        <span class="order-summary-value">{{ $order->delivery_size ?: 'غير محدد' }}</span>
                    </li>
                    <li>
                        <span class="order-summary-label">سعر التوصيل</span>
                        <span class="order-summary-value">{{ number_format($order->delivery_total ?? 0, 2) }} {{ $currencyCode }}</span>
                    </li>
                </ul>
                @if($manualPaymentLocked)
                    <div class="alert alert-warning mt-3 mb-0">
                        يوجد طلب دفع يدوي قيد المراجعة، لا يمكن تغيير حالة الطلب حتى إكمال المراجعة.
                    </div>
                @endif
            </section>

            <section class="order-summary-block">
                <h6 class="order-summary-heading">تحديث حالة الطلب</h6>
                <div class="form-group mb-3">
                    <label for="order_status" class="form-label">حالة الطلب</label>
                    @if($canUpdateStatus)
                        <select class="form-control" id="order_status" name="order_status" required>
                            @foreach($orderStatuses as $status)
                                <option value="{{ $status->code }}" {{ $order->order_status === $status->code ? 'selected' : '' }}>
                                    {{ $status->name }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="order_status" value="{{ $order->order_status }}">
                        <div class="form-control-plaintext border rounded bg-light px-3 py-2">
                            {{ $statusLabel }}
                        </div>
                    @endif
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">حالة الدفع</label>
                    <div class="form-control-plaintext border rounded bg-light px-3 py-2">
                        {{ $paymentStatusLabel }}
                    </div>
                    <small class="text-muted d-block mt-2">يتم تحديث حالة الدفع تلقائياً من النظام.</small>
                </div>

                <div class="form-group mb-3">
                    <label for="comment" class="form-label">ملاحظة التحديث</label>
                    <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="اكتب سبب التحديث أو ملاحظة مختصرة">{{ old('comment') }}</textarea>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="notify_customer" name="notify_customer" value="1" {{ old('notify_customer') ? 'checked' : '' }}>
                    <label class="form-check-label" for="notify_customer">إرسال إشعار للعميل</label>
                </div>

                <div class="order-tracking-group">
                    <div class="form-group mb-3">
                        <label for="tracking_number" class="form-label">رقم التتبع</label>
                        <input type="text" class="form-control" id="tracking_number" name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}">
                    </div>
                    <div class="form-group mb-3">
                        <label for="carrier_name" class="form-label">شركة الشحن</label>
                        <input type="text" class="form-control" id="carrier_name" name="carrier_name" value="{{ old('carrier_name', $order->carrier_name) }}">
                    </div>
                    <div class="form-group mb-3">
                        <label for="tracking_url" class="form-label">رابط التتبع</label>
                        <input type="url" class="form-control" id="tracking_url" name="tracking_url" value="{{ old('tracking_url', $order->tracking_url) }}">
                    </div>
                </div>

                <div class="order-proof-group">
                    <div class="form-group mb-3">
                        <label for="delivery_proof_image_path" class="form-label">مسار صورة الإثبات</label>
                        <input type="text" class="form-control" id="delivery_proof_image_path" name="delivery_proof_image_path" value="{{ old('delivery_proof_image_path', $order->delivery_proof_image_path) }}">
                    </div>
                    <div class="form-group mb-3">
                        <label for="delivery_proof_signature_path" class="form-label">مسار التوقيع</label>
                        <input type="text" class="form-control" id="delivery_proof_signature_path" name="delivery_proof_signature_path" value="{{ old('delivery_proof_signature_path', $order->delivery_proof_signature_path) }}">
                    </div>
                    <div class="form-group mb-0">
                        <label for="delivery_proof_otp_code" class="form-label">رمز التسليم</label>
                        <input type="text" class="form-control" id="delivery_proof_otp_code" name="delivery_proof_otp_code" value="{{ old('delivery_proof_otp_code', $order->delivery_proof_otp_code) }}">
                    </div>
                </div>

                @if($orderStatusLockMessage)
                    <div class="alert alert-info mt-3 mb-0">
                        {{ $orderStatusLockMessage }}
                    </div>
                @endif
            </section>

            <section class="order-summary-block">
                <h6 class="order-summary-heading">بيانات الشحن والملاحظات</h6>
                <div class="form-group mb-3">
                    <label for="shipping_address" class="form-label">عنوان الشحن</label>
                    <textarea class="form-control" id="shipping_address" name="shipping_address" rows="3">{{ old('shipping_address', $order->shipping_address) }}</textarea>
                </div>
                <div class="form-group mb-3">
                    <label for="billing_address" class="form-label">عنوان الفاتورة</label>
                    <textarea class="form-control" id="billing_address" name="billing_address" rows="3">{{ old('billing_address', $order->billing_address) }}</textarea>
                </div>
                <div class="form-group mb-0">
                    <label for="notes" class="form-label">ملاحظات الطلب</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $order->notes) }}</textarea>
                </div>
            </section>

            <section class="order-summary-block">
                <h6 class="order-summary-heading">العميل والتاجر</h6>
                <div class="order-summary-parties">
                    <div>
                        <div class="order-party-title">العميل</div>
                        <ul class="order-summary-list order-summary-list--compact">
                            <li>
                                <span class="order-summary-label">الاسم</span>
                                <span class="order-summary-value">{{ optional($order->user)->name ?? 'غير متوفر' }}</span>
                            </li>
                            <li>
                                <span class="order-summary-label">رقم الهاتف</span>
                                <span class="order-summary-value">{{ optional($order->user)->mobile ?? 'غير متوفر' }}</span>
                            </li>
                            <li>
                                <span class="order-summary-label">البريد الإلكتروني</span>
                                <span class="order-summary-value">{{ optional($order->user)->email ?? 'غير متوفر' }}</span>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <div class="order-party-title">التاجر</div>
                        <ul class="order-summary-list order-summary-list--compact">
                            <li>
                                <span class="order-summary-label">الاسم</span>
                                <span class="order-summary-value">{{ optional($order->seller)->name ?? 'غير متوفر' }}</span>
                            </li>
                            <li>
                                <span class="order-summary-label">رقم الهاتف</span>
                                <span class="order-summary-value">{{ optional($order->seller)->mobile ?? 'غير متوفر' }}</span>
                            </li>
                            <li>
                                <span class="order-summary-label">البريد الإلكتروني</span>
                                <span class="order-summary-value">{{ optional($order->seller)->email ?? 'غير متوفر' }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            @if($orderNotes !== '')
                <section class="order-summary-block order-summary-block--wide">
                    <h6 class="order-summary-heading">الملاحظات الحالية</h6>
                    <p class="order-notes-text mb-0">{{ $orderNotes }}</p>
                </section>
            @endif
        </div>
    </form>

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
                                    التوجيه إلى شي ان
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
                                <p class="text-muted small mb-0">لا توجد خيارات لهذا المنتج.</p>
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
                                <span class="text-muted">لا توجد بيانات.</span>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="order-empty-state">
                    <p class="text-muted mb-0">لا توجد عناصر لهذا الطلب.</p>
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
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const statusField = document.getElementById('order_status');
            const statusFallback = document.querySelector('input[name="order_status"]');
            const trackingGroup = document.querySelector('.order-tracking-group');
            const proofGroup = document.querySelector('.order-proof-group');
            const outForDelivery = @json(Order::STATUS_OUT_FOR_DELIVERY);
            const delivered = @json(Order::STATUS_DELIVERED);

            const resolveStatus = () => {
                if (statusField) {
                    return statusField.value;
                }
                if (statusFallback) {
                    return statusFallback.value;
                }
                return '';
            };

            const setVisibility = () => {
                const status = resolveStatus();
                const showTracking = status === outForDelivery || status === delivered;
                const showProof = status === delivered;

                if (trackingGroup) {
                    trackingGroup.style.display = showTracking ? '' : 'none';
                }
                if (proofGroup) {
                    proofGroup.style.display = showProof ? '' : 'none';
                }
            };

            if (statusField) {
                statusField.addEventListener('change', setVisibility);
            }

            setVisibility();
        });
    </script>
@endpush

<style>
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
