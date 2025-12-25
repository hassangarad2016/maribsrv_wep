{{-- resources/views/services/show.blade.php --}}
@extends('layouts.main')

@section('title')
    {{ __('services.titles.details') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-end float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('services.index') }}" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-left"></i> {{ __('services.buttons.back_to_services') }}
                            </a>
                        </li>
                        @can('service-edit')
                        <li class="breadcrumb-item">
                            <a href="{{ route('services.edit', $service->id) }}" class="btn btn-primary">
                                <i class="bi bi-pencil"></i> {{ __('services.buttons.edit') }}
                            </a>
                        </li>
                        @endcan
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection

@section('content')
<section class="section">
    <div class="card">
        <div class="card-body">
            {{-- ======= بيانات أساسية ======= --}}
            <div class="row g-4">

                {{-- الصورة والأيقونة --}}
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label d-block">{{ __('services.labels.image') }}</label>
                        @if($service->image)
                            <img src="{{ asset('storage/' . $service->image) }}" alt="image" class="img-fluid rounded border" style="max-height: 200px">
                        @else
                            <span class="text-muted">{{ __('services.messages.no_image') }}</span>
                        @endif
                    </div>
                    <div>
                        <label class="form-label d-block">{{ __('services.labels.icon') }}</label>
                        @if($service->icon)
                            <img src="{{ asset('storage/' . $service->icon) }}" alt="icon" class="img-thumbnail rounded" style="height: 80px">
                        @else
                            <span class="text-muted">{{ __('services.messages.no_icon') }}</span>
                        @endif
                    </div>
                </div>

                {{-- الحقول النصية والبادجات --}}
                <div class="col-md-8">
                    <div class="row g-3">

                        <div class="col-12">
                            <h5 class="mb-1">{{ $service->title }}</h5>
                            <div class="small text-muted">
                                {{ __('services.labels.id') }}: {{ $service->id }}
                                @if(!empty($service->service_uid))
                                    &nbsp; &middot; &nbsp; {{ __('services.labels.uid') }}: <code>{{ $service->service_uid }}</code>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label d-block">{{ __('services.labels.category') }}</label>
                            <div>{{ optional($service->category)->name }} @if($service->category_id) <span class="text-muted">({{ __('services.labels.id') }}: {{ $service->category_id }})</span>@endif</div>
                        </div>



                        <div class="col-md-6">
                            <label class="form-label d-block">{{ __('services.labels.service_owner') }}</label>
                            @if($service->owner)
                                <div>{{ $service->owner->name }} <span class="text-muted">({{ __('services.labels.id') }}: {{ $service->owner->id }})</span></div>
                                @if($service->owner->email)
                                    <div class="small text-muted">{{ $service->owner->email }}</div>
                                @endif
                            @else
                                <span class="text-muted">{{ __('services.messages.no_owner') }}</span>
                            @endif
                        </div>


                        <div class="col-md-6">
                            <label class="form-label d-block">{{ __('services.labels.status') }}</label>
                            @if($service->status)
                                <span class="badge bg-success">{{ __('services.labels.active') }}</span>
                            @else
                                <span class="badge bg-danger">{{ __('services.labels.inactive') }}</span>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label d-block">{{ __('services.labels.is_main_service') }}</label>
                            @if($service->is_main)
                                <span class="badge bg-primary">{{ __('services.labels.yes') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('services.labels.no') }}</span>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label d-block">{{ __('services.labels.views') }}</label>
                            <div>{{ $service->views ?? 0 }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label d-block">{{ __('services.labels.expiry_date') }}</label>
                            <div>
                                {{ $service->expiry_date ? \Illuminate\Support\Carbon::parse($service->expiry_date)->format('Y-m-d') : __('services.labels.no_expiry') }}
                            </div>
                        </div>

                        {{-- ======= خيارات الدفع ======= --}}
                        <div class="col-12"><hr class="my-2"></div>

                        <div class="col-md-4">
                            <label class="form-label d-block">{{ __('services.labels.is_paid') }}</label>
                            @if($service->is_paid)
                                <span class="badge bg-success">{{ __('services.labels.yes') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('services.labels.no') }}</span>
                            @endif
                        </div>

                        <div class="col-md-4">
                            <label class="form-label d-block">{{ __('services.labels.price') }}</label>
                            <div>
                                @if($service->is_paid && $service->price !== null)
                                    {{ number_format((float)$service->price, 2) }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label d-block">{{ __('services.labels.currency') }}</label>
                            <div>
                                @if($service->is_paid && $service->currency)
                                    {{ $service->currency }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                        </div>

                        @if($service->is_paid && $service->price_note)
                        <div class="col-12">
                            <label class="form-label d-block">{{ __('services.labels.price_note') }}</label>
                            <div class="text-wrap">{{ $service->price_note }}</div>
                        </div>
                        @endif

                        {{-- ======= الحقول المخصصة ======= --}}
                        <div class="col-12"><hr class="my-2"></div>

                        <div class="col-md-6">
                            <label class="form-label d-block">{{ __('services.labels.has_custom_fields') }}</label>
                            @if($service->has_custom_fields)
                                <span class="badge bg-info">{{ __('services.labels.yes') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('services.labels.no') }}</span>
                            @endif
                            <div class="small text-muted mt-1">
                                {{ __('services.messages.custom_fields_notice') }}
                            </div>
                        </div>

                        {{-- ======= التوجيه للدردشة المباشرة ======= --}}
                        <div class="col-md-6">
                            <label class="form-label d-block">{{ __('services.labels.direct_to_user') }}</label>
                            @if($service->direct_to_user)
                                <span class="badge bg-info">{{ __('services.labels.yes') }}</span>
                                <div class="mt-1">
                                    <span class="text-muted">{{ __('services.labels.advertiser') }}:</span>
                                    @if(isset($service->directUser))
                                        {{ $service->directUser->name }} <span class="text-muted">({{ __('services.labels.id') }}: {{ $service->direct_user_id }})</span>
                                    @elseif($service->direct_user_id)
                                        <span class="text-muted">{{ __('services.labels.id') }}: {{ $service->direct_user_id }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </div>
                            @else
                                <span class="badge bg-secondary">{{ __('services.labels.no') }}</span>
                            @endif
                        </div>

                        {{-- ======= النوع (إن استُخدم) ======= --}}
                        @if(!empty($service->service_type))
                        <div class="col-md-6">
                            <label class="form-label d-block">{{ __('services.labels.service_type') }}</label>
                            <div>{{ $service->service_type }}</div>
                        </div>
                        @endif

                    </div>
                </div>

                {{-- الوصف --}}
                <div class="col-12">
                    <hr class="my-3">
                    <label class="form-label d-block">{{ __('services.labels.description') }}</label>
                    @if(!empty($service->description))
                        <div class="border rounded p-3 bg-light">
                            {!! $service->description !!}
                        </div>
                    @else
                        <div class="text-muted">{{ __('services.messages.no_description') }}</div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</section>
@endsection
