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
            {{-- SUMMARY_SECTIONS --}}
        </div>
    </form>

    <div class="order-items-section mt-4">
        {{-- ITEMS_SECTION --}}
    </div>
</div>
@endsection

@push('scripts')
    {{-- SCRIPTS_START --}}
@endpush

<style>
/* STYLES_START */
</style>
