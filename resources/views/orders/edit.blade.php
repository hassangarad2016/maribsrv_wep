@extends('layouts.main')

@php
    use App\Models\ManualPaymentRequest;
    use App\Models\Order;
@endphp

@section('title', 'تعديل الطلب #' . $order->order_number)

@section('content')
<div class="container-fluid order-details-page">
    {{-- CONTENT_START --}}
</div>
@endsection

@push('scripts')
    {{-- SCRIPTS_START --}}
@endpush

<style>
/* STYLES_START */
</style>
