@extends('layouts.main')

@section('title')
    {{ __('services.titles.requests') }}
@endsection

@section('css')
<style>
    .service-requests-page {
        background: linear-gradient(180deg, rgba(13, 110, 253, 0.07), rgba(13, 110, 253, 0.02));
        border: 1px solid rgba(15, 23, 42, 0.06);
        border-radius: 1.25rem;
        color: #212529;
        padding: 1.25rem;
    }
    .service-requests-shell {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .service-requests-hero {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .service-requests-title {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 700;
        color: #0f172a;
    }
    .service-requests-subtitle {
        margin: 0.35rem 0 0;
        font-size: 0.9rem;
        color: #6c757d;
    }
    .service-requests-metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 0.75rem;
    }
    .metric-card {
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
    .metric-card.category .metric-icon {
        color: #198754;
        background: rgba(25, 135, 84, 0.12);
    }
    .metric-card.review .metric-icon {
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
    .metric-value.text-truncate {
        max-width: 180px;
    }
    .service-requests-filters {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 1rem;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
    }
    .service-requests-filters .card-body {
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
    .status-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .status-tab {
        border: 1px solid rgba(15, 23, 42, 0.15);
        background: #ffffff;
        color: #495057;
        border-radius: 0.75rem;
        padding: 0.4rem 0.9rem;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .status-tab:hover {
        border-color: #0d6efd;
        color: #0d6efd;
    }
    .status-tab.active {
        background: #0d6efd;
        color: #ffffff;
        border-color: #0d6efd;
        box-shadow: 0 10px 18px rgba(13, 110, 253, 0.2);
    }
    .status-tab.active:hover {
        color: #ffffff;
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
        max-width: 320px;
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
    .service-requests-table {
        border-radius: 1rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        margin-bottom: 0;
        overflow: hidden;
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
    }
    .service-requests-table .card-header {
        background: #f8f9fb;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        padding: 0.9rem 1.1rem;
    }
    .service-requests-table .card-body {
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
    .service-requests-table .table {
        margin-bottom: 0;
    }
    .service-requests-table .table thead th {
        background: #f8f9fa;
        color: #212529;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        padding: 0.85rem 1rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .service-requests-table .table tbody td {
        padding: 0.85rem 1rem;
    }
    .service-requests-table .table tbody tr {
        transition: background-color 0.2s ease;
    }
    .service-requests-table .table tbody tr:hover {
        background-color: rgba(13, 110, 253, 0.04);
    }
    .service-requests-table .table-striped > tbody > tr:nth-of-type(odd) {
        background-color: rgba(15, 23, 42, 0.02);
    }
    #table_list { width: 100%; }

    @media (min-width: 992px) {
        .service-requests-hero {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }
        .service-requests-metrics {
            max-width: 720px;
        }
    }

    @media (max-width: 768px) {
        .service-requests-page {
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
        .metric-value.text-truncate {
            max-width: 140px;
        }
    }
</style>
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first"></div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section service-requests-page">
        @php
            $totalRequests = (int) ($stats['total'] ?? 0);
            $reviewRequests = (int) ($stats['review'] ?? 0);
        @endphp
        <div class="service-requests-shell">
            <div class="service-requests-hero">
                <div>
                    <h5 class="service-requests-title">@yield('title')</h5>
                    <p class="service-requests-subtitle">{{ __('services.messages.requests_subtitle') }}</p>
                </div>
                <div class="service-requests-metrics">
                    <div class="metric-card category">
                        <span class="metric-icon"><i class="bi bi-folder2-open"></i></span>
                        <div>
                            <div class="metric-label">{{ __('services.labels.category') }}</div>
                            <div class="metric-value text-truncate" title="{{ $selectedCategory ? $selectedCategory->name : __('services.filters.all_categories') }}">
                                @if($selectedCategory)
                                    {{ $selectedCategory->name }}
                                @else
                                    {{ __('services.filters.all_categories') }}
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <span class="metric-icon"><i class="bi bi-collection"></i></span>
                        <div>
                            <div class="metric-label">{{ __('services.labels.total_requests') }}</div>
                            <div class="metric-value">{{ number_format($totalRequests) }}</div>
                        </div>
                    </div>
                    <div class="metric-card review">
                        <span class="metric-icon"><i class="bi bi-hourglass-split"></i></span>
                        <div>
                            <div class="metric-label">{{ __('services.labels.under_review') }}</div>
                            <div class="metric-value">{{ number_format($reviewRequests) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card service-requests-filters">
                <div class="card-body">
                    <div class="filters-header">
                        <div>
                            <h6 class="filters-title">{{ __('services.buttons.filter') }}</h6>
                            <p class="filters-hint">{{ __('services.messages.filters_hint') }}</p>
                        </div>
                    </div>
                    <div class="filters-row">
                        <div class="status-tabs" role="tablist">
                            <button type="button" class="status-tab active" data-status="">{{ __('services.filters.all') }}</button>
                            <button type="button" class="status-tab" data-status="review">{{ __('services.labels.under_review') }}</button>
                            <button type="button" class="status-tab" data-status="approved">{{ __('services.labels.approved') }}</button>
                            <button type="button" class="status-tab" data-status="rejected">{{ __('services.labels.rejected') }}</button>
                        </div>
                        <input type="hidden" id="status_filter" value="">
                        <div class="search-group">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="request_number" placeholder="{{ __('services.placeholders.transaction_number') }}" autocomplete="off">
                            </div>
                            <button class="btn btn-primary" type="button" id="requestNumberApply">{{ __('services.buttons.search') }}</button>
                            <button class="btn btn-outline-secondary" type="button" id="requestNumberReset">{{ __('services.buttons.reset') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card service-requests-table">
                <div class="card-header">
                    <div>
                        <h6 class="table-title">{{ __('services.labels.service_requests') }}</h6>
                        <p class="table-hint">{{ __('services.messages.table_hint') }}</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table
                           class="table table-striped table-hover align-middle"
                           aria-describedby="serviceRequestsTableCaption"
                           id="table_list"
                           data-toggle="table"
                           data-url="{{ route('service.requests.datatable') }}"
                           data-click-to-select="true"
                           data-side-pagination="server"
                           data-pagination="true"
                           data-page-list="[5, 10, 20, 50, 100, 200]"
                           data-search="false"
                           data-show-columns="false"
                           data-show-refresh="false"
                           data-trim-on-search="false"
                           data-escape="true"
                           data-responsive="true"
                           data-sort-name="id"
                           data-sort-order="desc"
                           data-pagination-successively-size="3"
                           data-table="items"
                           data-status-column="deleted_at"
                           data-show-export="false"
                           data-export-options='{"fileName": "service-requests-list","ignoreColumn": ["operate"]}'
                           data-export-types='["pdf","json","xml","csv","txt","sql","doc","excel"]'
                           data-mobile-responsive="true"
                           data-query-params="queryParams">
                            <thead>
                            <tr>
                                <th data-field="request_number" data-sortable="true" data-sort-name="request_number" data-formatter="requestNumberFormatter">{{ __('services.labels.transaction_identifier') }}</th>
                                <th data-field="id" data-sortable="true" data-visible="false">{{ __('services.labels.id') }}</th>

                                <th data-field="name" data-sortable="true">{{ __('services.labels.name') }}</th>

                                <th data-field="custom_fields" data-sortable="false" data-escape="false" data-formatter="customFieldsFormatter" data-events="fieldsEvents">{{ __('services.labels.filled_fields') }}</th>

                                <th data-field="submitted_at" data-sortable="true" data-sort-name="created_at" data-formatter="submissionDateFormatter">{{ __('services.labels.submitted_at') }}</th>
                                <th data-field="category.name" data-sortable="true" data-visible="false" data-formatter="serviceTypeFormatter">{{ __('services.labels.service_type') }}</th>
                                <th data-field="description" data-align="center" data-sortable="true" data-visible="false" data-formatter="descriptionFormatter">{{ __('services.labels.description') }}</th>
                                <th data-field="user.name" data-sort-name="user_name" data-sortable="true" data-visible="false">{{ __('services.labels.user') }}</th>
                                <th data-field="status" data-sortable="true" data-filter-control="select" data-escape="false" data-visible="false" data-formatter="itemStatusFormatter">{{ __('services.labels.status') }}</th>

                                <th data-field="rejected_reason" data-sortable="true" data-visible="false">{{ __('services.labels.rejected_reason') }}</th>

                                <th data-field="created_at" data-sortable="true" data-visible="false">{{ __('services.labels.created_at') }}</th>
                                <th data-field="updated_at" data-sortable="true" data-visible="false">{{ __('services.labels.updated_at') }}</th>
                                <th data-field="user_id" data-sortable="true" data-visible="false">{{ __('services.labels.user_id') }}</th>
                                <th data-field="category_id" data-sortable="true" data-visible="false">{{ __('services.labels.category_id') }}</th>

                                @canany(['service-requests-list','service-requests-update'])
                                    <th data-field="operate" data-align="center" data-sortable="false" data-events="itemEvents" data-escape="false">{{ __('services.labels.actions') }}</th>
                                @endcanany
                            </tr>
                            </thead>
                        </table>
                    </div>
                    <div id="serviceRequestsTableCaption" class="visually-hidden">{{ __('services.messages.table_hint') }}</div>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="myModalLabel1">{{ __('services.labels.request_details') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('services.buttons.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="center" id="custom_fields"></div>
                    </div>
                </div>
            </div>
        </div>

        <div id="editStatusModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="myModalLabel1">{{ __('services.labels.status') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('services.buttons.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <form class="edit-form" action="" method="POST" data-success-function="updateApprovalSuccess">
                            @csrf
                            <div class="row">
                                <div class="col-md-12">
                                    <select name="status" class="form-select" id="status" aria-label="status">
                                        <option value="review">{{ __('services.labels.under_review') }}</option>
                                        <option value="approved">{{ __('services.buttons.approve') }}</option>
                                        <option value="rejected">{{ __('services.buttons.reject') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div id="rejected_reason_container" class="col-md-12" style="display:none;">
                                <label for="rejected_reason" class="mandatory form-label">{{ __('services.labels.reason') }}</label>
                                <textarea name="rejected_reason" id="rejected_reason" class="form-control" placeholder="{{ __('services.labels.reason') }}"></textarea>
                            </div>
                            <input type="submit" value="{{ __('services.buttons.save') }}" class="btn btn-primary mt-3">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('script')
<script>
    function updateApprovalSuccess() { $('#editStatusModal').modal('hide'); }
    const CATEGORY_ID = @json($selectedCategoryId);

    // ط§ط³ظ… ط§ظ„ظپط¦ط© ظƒط¨ط§ط¯ط¬
    function serviceTypeFormatter(value, row) {
        if (row.category && row.category.name) {
            return '<span class="badge bg-light-primary">' + row.category.name + '</span>';
        }
        return '<span class="badge bg-light-secondary">-</span>';
    }


    function requestNumberFormatter(value, row) {
        var reference = value || (row && row.id ? ('#' + row.id) : '-');
        return '<span class="badge bg-primary text-white fw-semibold px-3 py-2">' + escapeHtml(reference) + '</span>';
    }

    // ط²ط± "ط¹ط±ط¶ ط§ظ„ط­ظ‚ظˆظ„" + ط¹ط¯ظ‘ط§ط¯
    function customFieldsFormatter(value, row) {
        var count = Array.isArray(row.custom_fields) ? row.custom_fields.length : 0;
        return '<button class="btn btn-sm btn-outline-secondary view-fields">'+
                   '{{ __('services.buttons.view') }}'+
               '</button> ' +
               '<span class="badge bg-light text-dark ms-1">'+ count +'</span>';
    }


    function submissionDateFormatter(value) {
        if (!value) {
            return '<span class="text-muted">-</span>';
        }
        return '<span class="text-nowrap">' + escapeHtml(value) + '</span>';
    }

    // ط¨ظ†ط§ط، ط¬ط¯ظˆظ„ ط§ظ„ط­ظ‚ظˆظ„ ط¯ط§ط®ظ„ ط§ظ„ظ…ظˆط¯ط§ظ„
    function renderCustomFieldsTable(fields) {
        if (!Array.isArray(fields) || !fields.length) {
            return '<div class="text-muted">{{ __('services.messages.no_custom_fields_filled') }}</div>';
        }
        var html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr>'+
                   '<th style="width:30%">{{ __('services.labels.field') }}</th><th>{{ __('services.labels.value') }}</th></tr></thead><tbody>';

        fields.forEach(function(f) {
            var label = f.label || f.name || f.key || '-';
            var val   = '';
            if (Array.isArray(f.values) && f.values.length) {
                val = f.values.join(', ');
            } else if (typeof f.value === 'object' && f.value !== null) {
                try { val = Object.values(f.value).join(', '); } catch(e) { val = JSON.stringify(f.value); }
            } else {
                val = (f.value !== undefined && f.value !== null) ? String(f.value) : (f.text || f.display || '-');
            }
            html += '<tr><td><strong>'+ escapeHtml(label) +'</strong></td><td>'+ escapeHtml(val) +'</td></tr>';
        });

        html += '</tbody></table></div>';
        return html;
    }

    // ط£ط­ط¯ط§ط« ط¹ظ…ظˆط¯ ط§ظ„ط­ظ‚ظˆظ„
    window.fieldsEvents = {
        'click .view-fields': function (e, value, row, index) {
            var html = renderCustomFieldsTable(row.custom_fields || row.attributes || []);
            $('#custom_fields').html(html);
            $('#editModal').modal('show');
        }
    };

    // طھظ…ط±ظٹط± ط§ظ„ظپظ„ط§طھط± ظ„ظ„ط³ظٹط±ظپط±
    function queryParams(params) {
        const query = {
            status_filter: $('#status_filter').val(),
            request_number: ($('#request_number').val() || '').trim(),
            offset: params.offset,
            limit: params.limit,
            search: params.search,
            sort: params.sort,
            order: params.order,
            filter: params.filter
        };

        if (CATEGORY_ID !== null && CATEGORY_ID !== undefined && CATEGORY_ID !== '') {
            query.category_id = CATEGORY_ID;
        }

        return query;
    }
    // ط£ط¯ظˆط§طھ ظ…ط³ط§ط¹ط¯ط©
    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"'`=\/]/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c];
        });
    }

    $(document).ready(function() {
        const $table = $('#table_list');
        const $requestNumber = $('#request_number');
        const $statusFilter = $('#status_filter');

        function refreshTableToFirstPage() {
            const options = $table.bootstrapTable('getOptions');
            options.pageNumber = 1;
            $table.bootstrapTable('refresh');
        }

        $('.status-tab').on('click', function () {
            $('.status-tab').removeClass('active');
            $(this).addClass('active');
            $statusFilter.val($(this).data('status'));
            refreshTableToFirstPage();
        });

        $('#requestNumberApply').on('click', function () {
            refreshTableToFirstPage();
        });

        $('#requestNumberReset').on('click', function () {
            $requestNumber.val('');
            refreshTableToFirstPage();
        });

        $requestNumber.on('keypress', function (event) {
            if (event.which === 13) {
                event.preventDefault();
                refreshTableToFirstPage();
            }
        });

        $('#status').on('change', function() {
            $('#rejected_reason_container').toggle($(this).val() === 'rejected');
        });
    });
</script>
@endsection











