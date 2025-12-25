@extends('layouts.main')

@section('title')
    {{ __('services.titles.requests') }}
@endsection

@section('page-style')
<style>
    .service-requests-page {
        color: #212529;
    }
    .service-requests-page .card {
        border-radius: 1rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
    }
    .service-requests-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .service-requests-title {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 700;
    }
    .service-requests-subtitle {
        margin: 0.25rem 0 0;
        color: #6c757d;
        font-size: 0.95rem;
    }
    .service-requests-header__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .service-requests-header__meta .badge {
        padding: 0.45rem 0.7rem;
        border-radius: 999px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .service-requests-filters {
        margin-bottom: 1.25rem;
    }
    .service-requests-filters .card-body {
        padding: 1.5rem;
    }
    .service-requests-filters__hint {
        margin: 0.75rem 0 0;
        color: #6c757d;
        font-size: 0.95rem;
    }

    #filters select,
    #filters input {
        height: 44px;
        font-size: 0.95rem;
        padding: 8px 12px;
        border-radius: 0.6rem;
        border: 1px solid rgba(15, 23, 42, 0.15);
        background-color: #ffffff;
    }
    #filters label {
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 6px;
    }
    #filters .form-control-plaintext {
        padding: 8px 12px;
        background: rgba(15, 23, 42, 0.04);
        border-radius: 0.6rem;
    }
    #filters .input-group {
        gap: 0.5rem;
    }
    #filters .input-group > .btn {
        height: 44px;
        border-radius: 0.6rem;
    }
    #table_list { width: 100%; }

    .btn-with-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        padding: 0.35rem 0.75rem;
        line-height: 1.2;
        width: auto;
        height: auto;
        white-space: nowrap;
    }
    .btn-with-label.btn-icon {
        width: auto;
        height: auto;
    }
    .btn-with-label .btn-label {
        display: inline-block;
    }

    .service-requests-table .card-body {
        padding: 1.6rem;
    }
    .service-requests-table__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }
    .service-requests-table__title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
    }
    .service-requests-table__hint {
        margin: 0.25rem 0 0;
        color: #6c757d;
        font-size: 0.95rem;
    }
    .service-requests-table .table {
        margin-bottom: 0;
    }
    .service-requests-table .table thead th {
        background: #f8f9fa;
        color: #212529;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        padding: 1rem;
        font-weight: 600;
    }
    .service-requests-table .table tbody td {
        padding: 0.9rem 1rem;
    }
    .service-requests-table .table-striped > tbody > tr:nth-of-type(odd) {
        background-color: rgba(15, 23, 42, 0.02);
    }

    @media (max-width: 768px) {
        .service-requests-header,
        .service-requests-table__header {
            flex-direction: column;
            align-items: flex-start;
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
        @endphp

        <div class="card service-requests-filters">
            <div class="card-body">
                <div class="service-requests-header">
                    <div>
                        <h5 class="service-requests-title">@yield('title')</h5>
                        <p class="service-requests-subtitle">{{ __('services.messages.requests_subtitle') }}</p>
                    </div>
                    <div class="service-requests-header__meta">
                        <span class="badge bg-light-primary">
                            {{ __('services.labels.category') }}:
                            @if($selectedCategory)
                                {{ $selectedCategory->name }}
                            @else
                                {{ __('services.filters.all_categories') }}
                            @endif
                        </span>
                        <span class="badge bg-light-secondary">
                            {{ __('services.labels.total_requests') }}: {{ number_format($totalRequests) }}
                        </span>
                        <span class="badge bg-light-warning">
                            {{ __('services.labels.under_review') }}: {{ number_format((int) ($stats['review'] ?? 0)) }}
                        </span>
                    </div>
                </div>

                <p class="service-requests-filters__hint">{{ __('services.messages.filters_hint') }}</p>

                <div id="filters" class="row g-3 align-items-end mt-2">
                    <div class="col-sm-6 col-lg-3">
                        <label for="filter" class="d-block">{{ __('services.labels.status') }}</label>
                        <select class="form-control bootstrap-table-filter-control-status" id="filter">
                            <option value="">{{ __('services.filters.all') }}</option>
                            <option value="review">{{ __('services.labels.under_review') }}</option>
                            <option value="approved">{{ __('services.labels.approved') }}</option>
                            <option value="rejected">{{ __('services.labels.rejected') }}</option>
                            <option value="sold out">{{ __('services.labels.sold_out') }}</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="d-block">{{ __('services.labels.category') }}</label>
                        @if($selectedCategory)
                            <div class="form-control-plaintext fw-semibold">{{ $selectedCategory->name }}</div>
                        @else
                            <div class="form-control-plaintext text-muted">{{ __('services.filters.all_categories') }}</div>
                        @endif
                    </div>

                    <div class="col-12 col-lg-6">
                        <label for="request_number" class="d-block">{{ __('services.labels.search_by_transaction_number') }}</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="request_number" placeholder="{{ __('services.placeholders.transaction_number') }}" autocomplete="off">
                            <button class="btn btn-outline-primary" type="button" id="requestNumberApply">{{ __('services.buttons.search') }}</button>
                            <button class="btn btn-outline-secondary" type="button" id="requestNumberReset">{{ __('services.buttons.reset') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card service-requests-table">
            <div class="card-body">
                <div class="service-requests-table__header">
                    <div>
                        <h5 class="service-requests-table__title">{{ __('services.labels.service_requests') }}</h5>
                        <p class="service-requests-table__hint">{{ __('services.messages.table_hint') }}</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table
                       class="table table-striped table-hover align-middle"
                       aria-describedby="mydesc"
                       id="table_list"
                       data-toggle="table"
                       data-url="{{ route('service.requests.datatable') }}"
                       data-click-to-select="true"
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
                       data-table="items"
                       data-status-column="deleted_at"
                       data-show-export="true"
                       data-export-options='{"fileName": "service-requests-list","ignoreColumn": ["operate"]}'
                       data-export-types='["pdf","json","xml","csv","txt","sql","doc","excel"]'
                       data-mobile-responsive="true"
                       data-filter-control="true"
                       data-filter-control-container="#filters"
                       data-toolbar="#filters"
                       data-query-params="queryParams">
                        <thead class="service-requests-table__head">
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

        {{-- ظ…ظˆط¯ط§ظ„ طھط؛ظٹظٹط± ط§ظ„ط­ط§ظ„ط© --}}
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

            status_filter: $('#filter').val(),
            offset: params.offset,
            limit: params.limit,
            search: params.search,
            sort: params.sort,
            order: params.order,
            filter: params.filter
        };

        const requestNumberValue = $('#request_number').val();
        query.request_number = requestNumberValue ? requestNumberValue.trim() : '';

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

        function refreshTableToFirstPage() {
            const options = $table.bootstrapTable('getOptions');
            options.pageNumber = 1;
            $table.bootstrapTable('refresh');
        }


        // طھط­ط¯ظٹط« ط§ظ„ط¬ط¯ظˆظ„ ط¹ظ†ط¯ طھط؛ظٹظٹط± ط§ظ„ظپظ„ط§طھط±
        $('#filter').on('change', function() {
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

        // ط¥ط¸ظ‡ط§ط±/ط¥ط®ظپط§ط، ط³ط¨ط¨ ط§ظ„ط±ظپط¶
        $('#status').on('change', function() {
            $('#rejected_reason_container').toggle($(this).val() === 'rejected');
        });
    });
</script>
@endsection





