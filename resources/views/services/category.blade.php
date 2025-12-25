{{-- resources/views/services/category.blade.php --}}
@extends('layouts.main')




@section('css')
    <style>
        .service-card .line-clamp-3 {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
            word-break: break-word;
        }


        .nav-tabs .nav-link {
            font-weight: 600;
        }

        .nav-tabs .nav-link .badge {
            font-weight: 500;
        }

        .tab-pane {
            min-height: 200px;
        }
    </style>
@endsection



@section('title')
    {{ __('services.titles.category', ['category' => $category->name]) }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
                <p class="text-muted mb-0">{{ __('services.messages.viewing_category', ['category' => $category->name]) }}</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <div class="float-end d-flex gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('services.index') }}">
                        <i class="bi bi-arrow-left"></i> {{ __('services.buttons.back_to_categories') }}
                    </a>
                    @can('service-create')
                        <a class="btn btn-primary" href="{{ route('services.create', ['category_id' => $category->id]) }}">
                            <i class="bi bi-plus-circle"></i> {{ __('services.buttons.create_service') }}
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endsection




@php

    $canManageCategoryRequests = $canManageCategoryRequests ?? false;

    $servicePermissions = [
        'view' => auth()->user()?->can('service-list') ?? false,
        'edit' => auth()->user()?->can('service-edit') ?? false,
        'delete' => auth()->user()?->can('service-delete') ?? false,
        'manageRequests' => auth()->user()?->can('service-requests-list') ?? false,
        'requestsCreate' => auth()->user()?->can('service-requests-create') ?? false,
        'requestsUpdate' => auth()->user()?->can('service-requests-update') ?? false,
        'requestsDelete' => auth()->user()?->can('service-requests-delete') ?? false,



        'manageManagers' => auth()->user()?->can('service-managers-manage') ?? false,



    ];



    $canAccessRequestsIndex = $supportsServiceRequests && (
        $servicePermissions['manageRequests'] ||
        $servicePermissions['requestsUpdate'] ||
        $servicePermissions['requestsDelete']
    );

@endphp






@section('content')
    <section class="section">
        <div class="card">
            <div class="card-body">


                <ul class="nav nav-tabs" id="categoryTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="services-tab" data-bs-toggle="tab" data-bs-target="#services-pane"
                                type="button" role="tab" aria-controls="services-pane" aria-selected="true">
                            <i class="bi bi-grid"></i>
                            <span class="ms-1">{{ __('services.tabs.services') }}</span>
                        </button>
                    </li>

                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews-pane"
                                type="button" role="tab" aria-controls="reviews-pane" aria-selected="false">
                            <i class="bi bi-chat-quote"></i>
                            <span class="ms-1">{{ __('services.tabs.reviews_reports') }}</span>
                        </button>
                    </li>


                    @if ($canAccessRequestsIndex)
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" href="{{ route('service.requests.index', ['category_id' => $category->id]) }}">
                                <i class="bi bi-list-check"></i>
                                <span class="ms-1">{{ __('services.tabs.requests') }}</span>
                            </a>
                        </li>
                    @endif

                </ul>

                <div class="tab-content pt-4" id="categoryTabsContent">
                    <div class="tab-pane fade show active" id="services-pane" role="tabpanel" aria-labelledby="services-tab">
                        <form id="servicesFilterForm" class="row g-3 mb-4">
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="form-label">{{ __('services.labels.category') }}</label>
                                <div class="form-control-plaintext fw-semibold">{{ $category->name }}</div>
                                <input type="hidden" name="category_id" value="{{ $category->id }}">
                            </div>





                            <div class="col-12 col-sm-6 col-lg-2">
                                <label for="status_filter" class="form-label">{{ __('services.labels.status') }}</label>
                                <select id="status_filter" name="status" class="form-select">
                                    <option value="">{{ __('services.filters.all_status') }}</option>
                                    <option value="1">{{ __('services.labels.active') }}</option>
                                    <option value="0">{{ __('services.labels.inactive') }}</option>
                                </select>
                            </div>

                            <div class="col-12 col-sm-6 col-lg-2">
                                <label for="is_main_filter" class="form-label">{{ __('services.labels.is_main') }}</label>
                                <select id="is_main_filter" name="is_main" class="form-select">
                                    <option value="">{{ __('services.filters.all') }}</option>
                                    <option value="1">{{ __('services.labels.yes') }}</option>
                                    <option value="0">{{ __('services.labels.no') }}</option>
                                </select>
                            </div>




                            <div class="col-12 col-sm-6 col-lg-2">
                                <label for="is_paid_filter" class="form-label">{{ __('services.labels.is_paid') }}</label>
                                <select id="is_paid_filter" name="is_paid" class="form-select">
                                    <option value="">{{ __('services.filters.all') }}</option>
                                    <option value="1">{{ __('services.labels.yes') }}</option>
                                    <option value="0">{{ __('services.labels.no') }}</option>
                                </select>
                            </div>



                            <div class="col-12 col-sm-6 col-lg-2">
                                <label for="has_cf_filter" class="form-label">{{ __('services.labels.has_custom_fields') }}</label>
                                <select id="has_cf_filter" name="has_custom_fields" class="form-select">
                                    <option value="">{{ __('services.filters.all') }}</option>
                                    <option value="1">{{ __('services.labels.yes') }}</option>
                                    <option value="0">{{ __('services.labels.no') }}</option>
                                </select>
                            </div>




                            <div class="col-12 col-sm-6 col-lg-2">
                                <label for="direct_user_filter" class="form-label">{{ __('services.labels.direct_to_user') }}</label>
                                <select id="direct_user_filter" name="direct_to_user" class="form-select">
                                    <option value="">{{ __('services.filters.all') }}</option>
                                    <option value="1">{{ __('services.labels.yes') }}</option>
                                    <option value="0">{{ __('services.labels.no') }}</option>
                                </select>
                            </div>




                            <div class="col-12 col-lg-3 ms-auto">
                                <label class="form-label d-none d-lg-block">&nbsp;</label>
                                <button type="submit" class="btn btn-secondary w-100">
                                    <i class="bi bi-funnel"></i> {{ __('services.buttons.filter') }}
                                </button>
                            </div>
                        </form>




                        <div id="servicesFeedback" class="alert alert-danger d-none" role="alert"></div>

                        <div id="servicesEmptyState" class="text-center text-muted py-5 {{ empty($initialServices) ? '' : 'd-none' }}">
                            <i class="bi bi-grid-3x3-gap display-6 d-block mb-3"></i>
                            <p class="mb-2">{{ __('services.messages.no_data_found') }}</p>
                            <p class="mb-0 small">{{ __('services.messages.adjust_filters') }}</p>
                        </div>

                        <div id="servicesCardsContainer" class="row g-4"></div>



                    </div>



                    <div class="tab-pane fade" id="reviews-pane" role="tabpanel" aria-labelledby="reviews-tab">
                        <div id="reviewsFilters" class="row g-3 align-items-end mb-3">
                            <div class="col-12 col-md-4 col-lg-3">
                                <label for="reviews_status_filter" class="form-label">{{ __('services.labels.status') }}</label>
                                <select id="reviews_status_filter" class="form-select">
                                    <option value="">{{ __('services.filters.all') }}</option>
                                    <option value="pending">{{ __('services.labels.pending') }}</option>
                                    <option value="approved">{{ __('services.labels.approved') }}</option>
                                    <option value="rejected">{{ __('services.labels.rejected') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table
                                class="table-borderless table-striped"
                                aria-describedby="reviewsTableCaption"
                                id="reviewsTable"
                                data-toggle="table"
                                data-url="{{ route('services.category.reviews', $category) }}"
                                data-side-pagination="server"
                                data-pagination="true"
                                data-page-list="[5, 10, 20, 50, 100, 200]"
                                data-search="true"
                                data-show-columns="true"
                                data-show-refresh="true"
                                data-trim-on-search="false"
                                data-escape="true"
                                data-responsive="true"
                                data-sort-name="id"
                                data-sort-order="desc"
                                data-pagination-successively-size="3"
                                data-mobile-responsive="true"
                                data-query-params="categoryReviewsQueryParams"
                                data-toolbar="#reviewsFilters"
                            >
                                <thead class="thead-dark">
                                <tr>
                                    <th data-field="id" data-sortable="true">{{ __('services.labels.id') }}</th>
                                    <th data-field="service.title" data-sortable="true" data-formatter="reviewServiceFormatter" data-escape="false">{{ __('services.labels.service') }}</th>
                                    <th data-field="user.name" data-sortable="true" data-formatter="reviewUserFormatter" data-escape="false">{{ __('services.labels.user') }}</th>
                                    <th data-field="rating" data-sortable="true" data-formatter="reviewRatingFormatter" data-escape="false">{{ __('services.labels.rating') }}</th>
                                    <th data-field="status" data-sortable="true" data-formatter="reviewStatusFormatter" data-escape="false">{{ __('services.labels.status') }}</th>
                                    <th data-field="review" data-formatter="reviewTextFormatter" data-escape="false">{{ __('services.labels.review_text') }}</th>
                                    <th data-field="created_at" data-sortable="true">{{ __('services.labels.created_at') }}</th>
                                </tr>
                                </thead>
                            </table>
                        </div>

                        <div id="reviewsTableCaption" class="visually-hidden">{{ __('services.messages.service_reviews_caption') }}</div>
                    </div>
                </div>





            </div>
        </div>
    </section>




    <div class="modal fade" id="serviceInsightsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('services.labels.insights') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('services.buttons.close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="fw-semibold mb-3">{{ __('services.labels.details') }}</h6>
                            <dl class="row small mb-0">
                                <dt class="col-5 text-muted">{{ __('services.labels.title') }}</dt>
                                <dd class="col-7" id="insightTitle">-</dd>

                                <dt class="col-5 text-muted">{{ __('services.labels.category') }}</dt>
                                <dd class="col-7" id="insightCategory">-</dd>

                                <dt class="col-5 text-muted">{{ __('services.labels.status') }}</dt>
                                <dd class="col-7" id="insightStatus">-</dd>

                                <dt class="col-5 text-muted">{{ __('services.labels.is_main') }}</dt>
                                <dd class="col-7" id="insightIsMain">-</dd>

                                <dt class="col-5 text-muted">{{ __('services.labels.direct_to_user') }}</dt>
                                <dd class="col-7" id="insightDirectUser">-</dd>

                                <dt class="col-5 text-muted">{{ __('services.labels.service_uid') }}</dt>
                                <dd class="col-7" id="insightServiceUid">-</dd>

                                <dt class="col-5 text-muted">{{ __('services.labels.created_at') }}</dt>
                                <dd class="col-7" id="insightCreatedAt">-</dd>

                                <dt class="col-5 text-muted">{{ __('services.labels.updated_at') }}</dt>
                                <dd class="col-7" id="insightUpdatedAt">-</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-semibold mb-3">{{ __('services.labels.statistics') }}</h6>
                            <dl class="row small mb-0">
                                <dt class="col-5 text-muted">{{ __('services.labels.is_paid') }}</dt>
                                <dd class="col-7" id="insightIsPaid">-</dd>

                                <dt class="col-5 text-muted">{{ __('services.labels.price') }}</dt>
                                <dd class="col-7" id="insightPrice">-</dd>

                                <dt class="col-5 text-muted">{{ __('services.labels.views') }}</dt>
                                <dd class="col-7" id="insightViews">-</dd>

                                <dt class="col-5 text-muted">{{ __('services.labels.requests') }}</dt>
                                <dd class="col-7" id="insightRequests">-</dd>

                                <dt class="col-5 text-muted">{{ __('services.labels.last_request') }}</dt>
                                <dd class="col-7" id="insightLastRequest">-</dd>

                                <dt class="col-5 text-muted">{{ __('services.labels.last_request_by') }}</dt>
                                <dd class="col-7" id="insightLastRequestUser">-</dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('services.buttons.close') }}</button>
                </div>
            </div>
        </div>
    </div>



@endsection

@section('script')
<script>
    // مسار التخزين لبناء روابط الصور من المسار النسبي
    const STORAGE_BASE = "{{ asset('storage') }}/";

    const SERVICES_BASE_URL = "{{ url('services') }}";


    const CATEGORY_PAGE_URL = "{{ route('services.category', $category) }}";
    const CATEGORY_ROUTE_TEMPLATE = "{{ route('services.category', ['category' => '__CATEGORY__']) }}";
    const CATEGORY_ID = Number("{{ (int) $category->id }}");

    const CATEGORY_REVIEWS_URL = "{{ route('services.category.reviews', $category) }}";
    
    const STATUS_URL = "{{ route('common.status.change') }}";
    const CSRF_TOKEN = "{{ csrf_token() }}";
    const HAS_SERVICE_REQUESTS = @json($supportsServiceRequests);
    const INITIAL_SERVICES = @json($initialServices);
    const PERMISSIONS = @json($servicePermissions);

    const LABELS = {
        yes: "{{ __('services.labels.yes') }}",
        no: "{{ __('services.labels.no') }}",
        active: "{{ __('services.labels.active') }}",
        inactive: "{{ __('services.labels.inactive') }}",
        requests: "{{ __('services.labels.requests') }}",
        lastRequest: "{{ __('services.labels.last_request') }}",
        lastRequestBy: "{{ __('services.labels.last_request_by') }}",
        notAvailable: "{{ __('services.labels.not_available') }}",
        directUser: "{{ __('services.labels.direct_to_user') }}",
        category: "{{ __('services.labels.category') }}",
        free: "{{ __('services.labels.free') }}",
        paid: "{{ __('services.labels.paid') }}",
        views: "{{ __('services.labels.views') }}",
        publish: "{{ __('services.labels.publish') }}",
        unpublish: "{{ __('services.labels.unpublish') }}",
        delete: "{{ __('services.buttons.delete') }}",
        edit: "{{ __('services.buttons.edit') }}",
        show: "{{ __('services.buttons.view') }}",
        manageManagers: "{{ __('services.labels.manage_managers') }}",
        insights: "{{ __('services.labels.insights') }}",
        loading: "{{ __('services.messages.loading') }}",
        error: "{{ __('services.messages.something_wrong') }}",
        noData: "{{ __('services.messages.no_data_found') }}",
        createdAt: "{{ __('services.labels.created_at') }}",
        updatedAt: "{{ __('services.labels.updated_at') }}",
        price: "{{ __('services.labels.price') }}",
        pending: "{{ __('services.labels.pending') }}",
        approved: "{{ __('services.labels.approved') }}",
        rejected: "{{ __('services.labels.rejected') }}",
        review: "{{ __('services.labels.review') }}",
        underReview: "{{ __('services.labels.under_review') }}",
        soldOut: "{{ __('services.labels.sold_out') }}",
        report: "{{ __('services.labels.report') }}",
        reportReason: "{{ __('services.labels.report_reason') }}",
        userReport: "{{ __('services.labels.user_report') }}",
    };
    const servicesFilterForm = $('#servicesFilterForm');

    const servicesCardsContainer = $('#servicesCardsContainer');
    const servicesEmptyState = $('#servicesEmptyState');
    const servicesFeedback = $('#servicesFeedback');

    const cardsContainer = servicesCardsContainer;
    const emptyState = servicesEmptyState;
    const feedback = servicesFeedback;

    let servicesState = [];
    let servicesIndex = new Map();

    const showUrl = (id) => `${SERVICES_BASE_URL}/${id}`;
    const editUrl = (id) => `${SERVICES_BASE_URL}/${id}/edit`;
    const destroyUrl = (id) => `${SERVICES_BASE_URL}/${id}`;



    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }


    function imgUrl(path) {
        if (!path) return '';
        if (/^https?:\/\//i.test(path)) return path;
        return STORAGE_BASE + String(path).replace(/^\/+/, '');
    }

    function boolBadge(val) {

        return val
            ? `<span class="badge bg-success-subtle text-success border border-success-subtle">${LABELS.yes}</span>`
            : `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">${LABELS.no}</span>`;

    }

    function statusBadge(val) {
        return val
            ? `<span class="badge bg-success">${LABELS.active}</span>`
            : `<span class="badge bg-danger">${LABELS.inactive}</span>`;
    }





    function reviewStatusBadge(status) {
        switch (status) {
            case 'approved':
                return '<span class="badge bg-success">' + LABELS.approved + '</span>';
            case 'rejected':
                return '<span class="badge bg-danger">' + LABELS.rejected + '</span>';
            case 'pending':
            default:
                return '<span class="badge bg-warning text-dark">' + LABELS.pending + '</span>';
        }
    }

    function reviewServiceFormatter(value, row) {
        const title = value || row?.service?.title || LABELS.notAvailable;
        const id = row?.service?.id;
        const safeTitle = escapeHtml(title);
        if (id) {
            const href = `${SERVICES_BASE_URL}/${id}`;
            return `<a href="${href}" class="fw-semibold text-decoration-none">${safeTitle}</a>`;
        }
        return safeTitle;
    }

    function reviewUserFormatter(value, row) {
        const name = value || row?.user?.name || LABELS.notAvailable;
        return `<div class="d-flex align-items-center gap-2"><i class="bi bi-person-circle text-secondary"></i><span>${escapeHtml(name)}</span></div>`;
    }

    function reviewRatingFormatter(value, row) {
        if (row?.is_report || value === 'report') {
            return `<span class="badge bg-warning-subtle text-warning border border-warning-subtle">${LABELS.report}</span>`;
        }
        const numeric = Number(value);
        if (!Number.isFinite(numeric)) {
            return escapeHtml(value ?? LABELS.notAvailable);
        }
        return `<span class="fw-semibold">${numeric.toFixed(1)}</span> <i class="bi bi-star-fill text-warning"></i>`;
    }

    function reviewStatusFormatter(value, row) {
        if (row?.is_report || value === 'report') {
            return `<span class="badge bg-warning text-dark"><i class="bi bi-flag-fill me-1"></i>${LABELS.report}</span>`;
        }
        return reviewStatusBadge(value);
    }

    function reviewTextFormatter(value, row) {
        const text = (value ?? '').toString().trim();
        const label = row?.is_report ? LABELS.reportReason : LABELS.review;
        const icon = row?.is_report ? 'bi-flag' : 'bi-chat-dots';
        const content = text !== '' ? escapeHtml(text) : LABELS.userReport;
        const accent = row?.is_report ? 'text-warning' : 'text-secondary';
        return `
            <div class="d-flex align-items-start gap-2" dir="auto">
                <i class="bi ${icon} ${accent}"></i>
                <div class="text-break">
                    <div class="small text-muted">${label}</div>
                    <div class="fw-semibold">${content}</div>
                </div>
            </div>
        `;
    }




    function formatPrice(value, currency) {
        if (value === null || value === undefined) {
            return LABELS.notAvailable;
        }
        const numeric = Number(value);
        const formatted = Number.isFinite(numeric)
            ? numeric.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : value;
        return `${formatted}${currency ? ` ${escapeHtml(currency)}` : ''}`;
    }

    function formatDateTime(value) {
        if (!value) {
            return LABELS.notAvailable;
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return escapeHtml(value);
        }
        return date.toLocaleString();
    }

    function normalizeService(service) {
        return {
            ...service,
            status: Boolean(service.status),
            is_main: Boolean(service.is_main),
            is_paid: Boolean(service.is_paid),
            has_custom_fields: Boolean(service.has_custom_fields),
            direct_to_user: Boolean(service.direct_to_user),
            requests_count: Number(service.requests_count ?? 0),
            latest_request: service.latest_request || null,
        };
    }

    function buildActionButtons(service) {
        const actions = [];

        if (PERMISSIONS.view) {
            actions.push(`
                <a href="${showUrl(service.id)}" class="btn btn-sm btn-primary shadow-sm btn-view-service">
                    <i class="bi bi-eye"></i>
                    <span class="d-none d-md-inline ms-1">${LABELS.show}</span>
                </a>
            `);
        }

        if (PERMISSIONS.edit) {
            actions.push(`
                <a href="${editUrl(service.id)}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil"></i>
                    <span class="d-none d-md-inline ms-1">${LABELS.edit}</span>
                </a>
            `);

            const toggleLabel = service.status ? LABELS.unpublish : LABELS.publish;
            const toggleClass = service.status ? 'btn-outline-warning' : 'btn-outline-success';
            actions.push(`
                <button type="button" class="btn btn-sm ${toggleClass} btn-toggle-status" data-service-id="${service.id}" data-status="${service.status ? 1 : 0}">
                    <i class="bi bi-megaphone"></i>
                    <span class="d-none d-md-inline ms-1">${toggleLabel}</span>
                </button>
            `);
        }





        actions.push(`
            <button type="button" class="btn btn-sm btn-outline-dark btn-service-insights" data-service-id="${service.id}">
                <i class="bi bi-graph-up"></i>
                <span class="d-none d-md-inline ms-1">${LABELS.insights}</span>
            </button>
        `);

        if (PERMISSIONS.delete) {
            actions.push(`
                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-service" data-service-id="${service.id}">
                    <i class="bi bi-trash"></i>
                    <span class="d-none d-md-inline ms-1">${LABELS.delete}</span>
                </button>
            `);
        }

        return `<div class="d-flex flex-wrap gap-2">${actions.join('')}</div>`;
    }

    function buildServiceCard(service) {
        const imagePath = service.image ? imgUrl(service.image) : '';
        const categoryName = escapeHtml(service.category?.name ?? LABELS.notAvailable);
        const directUser = escapeHtml(service.direct_user?.name ?? LABELS.notAvailable);
        const requestsCount = HAS_SERVICE_REQUESTS ? service.requests_count : '—';
        const lastRequestText = HAS_SERVICE_REQUESTS && service.latest_request ? formatDateTime(service.latest_request.created_at) : LABELS.notAvailable;
        const lastRequestUser = HAS_SERVICE_REQUESTS && service.latest_request?.user?.name ? escapeHtml(service.latest_request.user.name) : LABELS.notAvailable;
        const description = service.description_plain ? escapeHtml(service.description_plain) : '';

        const imageHtml = imagePath
            ? `<img src="${imagePath}" class="card-img-top" alt="${escapeHtml(service.title)}">`
            : `<div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 160px;"><i class="bi bi-image text-muted fs-1"></i></div>`;

        return `
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 shadow-sm service-card" data-service-id="${service.id}">
                    <div class="position-relative">
                        ${imageHtml}
                        <span class="position-absolute top-0 end-0 m-2">${statusBadge(service.status)}</span>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="me-3">
                                <h5 class="card-title mb-1 text-truncate" title="${escapeHtml(service.title)}">${escapeHtml(service.title)}</h5>
                                <div class="text-muted small text-truncate" title="${categoryName}"><i class="bi bi-folder2-open me-1"></i>${categoryName}</div>
                            </div>
                            <div class="text-end small">
                                <div>${boolBadge(service.is_main)}</div>
                                <div class="mt-1">${service.is_paid ? `<span class="badge bg-warning-subtle text-warning border border-warning-subtle">${LABELS.paid}</span>` : `<span class="badge bg-light text-muted border">${LABELS.free}</span>`}</div>
                            </div>
                        </div>

                        ${description ? `<p class="card-text small text-muted line-clamp-3 text-break" dir="auto">${description}</p>` : ''}
                        <ul class="list-unstyled small mb-3 mt-auto">
                            <li class="d-flex justify-content-between">
                                <span><i class="bi bi-collection me-1"></i>${LABELS.requests}</span>
                                <span>${requestsCount}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span><i class="bi bi-bar-chart-line me-1"></i>${LABELS.views}</span>
                                <span>${Number(service.views ?? 0).toLocaleString()}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span><i class="bi bi-person-lines-fill me-1"></i>${LABELS.directUser}</span>
                                <span class="text-truncate ms-2" title="${directUser}">${directUser}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span><i class="bi bi-wallet2 me-1"></i>${LABELS.price}</span>
                                <span>${service.is_paid && service.price !== null ? formatPrice(service.price, service.currency) : LABELS.free}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span><i class="bi bi-clock-history me-1"></i>${LABELS.lastRequest}</span>
                                <span class="text-truncate ms-2" title="${lastRequestText}">${lastRequestText}</span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span><i class="bi bi-people me-1"></i>${LABELS.lastRequestBy}</span>
                                <span class="text-truncate ms-2" title="${lastRequestUser}">${lastRequestUser}</span>
                            </li>
                        </ul>

                        ${buildActionButtons(service)}
                    </div>
                </div>
            </div>
        `;
    }

    function renderServiceCards(services) {

        servicesFeedback.addClass('d-none').empty();

        servicesIndex = new Map();
        servicesState = (services || []).map(normalizeService);

        if (!servicesState.length) {
            servicesCardsContainer.empty();
            servicesEmptyState.removeClass('d-none');
            return;
        }

        servicesEmptyState.addClass('d-none');

        let html = '';
        servicesState.forEach((service) => {
            servicesIndex.set(service.id, service);
            html += buildServiceCard(service);
        });


        servicesCardsContainer.html(html);
    }

    function showLoadingState() {
        servicesFeedback.addClass('d-none').empty();
        servicesEmptyState.addClass('d-none');
        servicesCardsContainer.html(`
            <div class="col-12 text-center py-5">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">${LABELS.loading}</span>
                </div>
            </div>
        `);








        
    }



    function showError(message) {
        cardsContainer.empty();
        emptyState.addClass('d-none');
        feedback.removeClass('d-none').text(message || LABELS.error);
    }





    function loadServices(showLoader = true) {
        if (showLoader) {
            showLoadingState();
        }

        const requestData = servicesFilterForm.serializeArray().reduce((acc, field) => {
            acc[field.name] = field.value;
            return acc;
        }, {});

        if (!('category_id' in requestData)) {
            requestData.category_id = servicesFilterForm.find('[name="category_id"]').val();
        }

        $.ajax({
            url: "{{ route('services.list') }}",

            type: 'GET',
            data: requestData,


            success: function (response) {
                const rows = response?.rows ?? [];
                renderServiceCards(rows);
            },
            error: function () {
                showError(LABELS.error);
            }
        });
    }

    function toggleServiceStatus(id, currentStatus) {
        const nextStatus = currentStatus ? 0 : 1;
        const button = $(`.btn-toggle-status[data-service-id="${id}"]`);
        button.prop('disabled', true);

        $.ajax({
            url: STATUS_URL,
            type: 'POST',
            data: {
                _method: 'PUT',
                _token: CSRF_TOKEN,
                id,
                status: nextStatus,
                table: 'services',
            },
            success: function () {
                const service = servicesIndex.get(id);
                if (service) {
                    service.status = Boolean(nextStatus);
                    renderServiceCards(servicesState);
                } else {
                    loadServices(false);

                    
                }


            },
            error: function (xhr) {
                alert(xhr?.responseJSON?.message || LABELS.error);
            },
            complete: function () {
                button.prop('disabled', false);
            }
        });
    }


    function deleteService(id) {
        if (!confirm("{{ __('Are you sure you want to delete this service?') }}")) {
            return;
        }

        $.ajax({
            url: destroyUrl(id),
            type: 'POST',
            data: { _method: 'DELETE', _token: CSRF_TOKEN },
            success: function () {
                servicesState = servicesState.filter((service) => service.id !== id);
                renderServiceCards(servicesState);



            },


            error: function (xhr) {
                alert(xhr?.responseJSON?.message || LABELS.error);
            }
        });
    }




    function openServiceInsights(service) {
        $('#insightTitle').text(service.title || '-');
        $('#insightCategory').text(service.category?.name || LABELS.notAvailable);
        $('#insightStatus').html(statusBadge(service.status));
        $('#insightIsMain').html(boolBadge(service.is_main));
        $('#insightDirectUser').text(service.direct_user?.name || LABELS.notAvailable);
        $('#insightServiceUid').text(service.service_uid || LABELS.notAvailable);
        $('#insightCreatedAt').text(formatDateTime(service.created_at));
        $('#insightUpdatedAt').text(formatDateTime(service.updated_at));

        $('#insightIsPaid').text(service.is_paid ? LABELS.paid : LABELS.free);
        $('#insightPrice').text(service.is_paid && service.price !== null ? formatPrice(service.price, service.currency) : LABELS.free);
        $('#insightViews').text(Number(service.views ?? 0).toLocaleString());

        if (HAS_SERVICE_REQUESTS) {
            $('#insightRequests').text(service.requests_count ?? 0);
            $('#insightLastRequest').text(service.latest_request ? formatDateTime(service.latest_request.created_at) : LABELS.notAvailable);
            $('#insightLastRequestUser').text(service.latest_request?.user?.name || LABELS.notAvailable);
        } else {
            $('#insightRequests').text('—');
            $('#insightLastRequest').text(LABELS.notAvailable);
            $('#insightLastRequestUser').text(LABELS.notAvailable);
        }

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceInsightsModal'));
        modal.show();
    }


    $(function () {

        renderServiceCards(INITIAL_SERVICES);


        // إرسال المرشحات
        servicesFilterForm.on('submit', function (e) {
            e.preventDefault();
            loadServices();
        });

        const categoryFilter = servicesFilterForm.find('[name="category_id"]');
        categoryFilter.on('change', function () {

            const value = ($(this).val() || '').trim();
            if (value) {
                const target = CATEGORY_ROUTE_TEMPLATE.replace('__CATEGORY__', encodeURIComponent(value));
                window.location.href = target;
            } else {
                window.location.href = CATEGORY_PAGE_URL;
            }
        });

        // تغيير سريع يطلق البحث تلقائيًا
        $('#status_filter, #is_main_filter, #is_paid_filter, #has_cf_filter, #direct_user_filter')
            .on('change', function () { servicesFilterForm.trigger('submit'); });



        const reviewsTable = $('#reviewsTable');
        $('#reviews_status_filter').on('change', function () {
            if (reviewsTable.length) {
                reviewsTable.bootstrapTable('refresh', { silent: true });
            }
        });

        $(document).on('click', '.btn-toggle-status', function () {
            const id = Number($(this).data('serviceId'));
            const currentStatus = Boolean(Number($(this).data('status')));
            toggleServiceStatus(id, currentStatus);
        });

        $(document).on('click', '.btn-delete-service', function () {
            const id = Number($(this).data('serviceId'));
            deleteService(id);
        });


        $(document).on('click', '.btn-service-insights', function () {
            const id = Number($(this).data('serviceId'));
            const service = servicesIndex.get(id);
            if (service) {
                openServiceInsights(service);


            }
        });
       

    });



    function categoryReviewsQueryParams(params = {}) {
        const query = {
            ...params,
            category_id: CATEGORY_ID,
        };

        const status = $('#reviews_status_filter').val();
        if (status) {
            query.status = status;
        }

        return query;
    }

    window.categoryReviewsQueryParams = categoryReviewsQueryParams;

    window.deleteService = deleteService;
    window.reviewServiceFormatter = reviewServiceFormatter;
    window.reviewUserFormatter = reviewUserFormatter;
    window.reviewRatingFormatter = reviewRatingFormatter;
    window.reviewStatusFormatter = reviewStatusFormatter;
    window.reviewTextFormatter = reviewTextFormatter;


</script>
@endsection
