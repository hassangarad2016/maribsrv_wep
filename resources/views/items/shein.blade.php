@extends('layouts.main')

@section('title')
    {{ __('Shein Items') }}
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
    .search-group .form-select {
        min-width: 220px;
        max-width: 280px;
        height: 38px;
        font-size: 0.9rem;
        border-radius: 0.75rem;
        border-color: rgba(15, 23, 42, 0.12);
    }
    .search-group .select2-container {
        min-width: 220px;
        max-width: 280px;
    }
    .search-group .select2-container--bootstrap-5 .select2-selection--single {
        height: 38px;
        border-radius: 0.75rem;
        border-color: rgba(15, 23, 42, 0.12);
    }
    .search-group .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        line-height: 36px;
        padding-inline-start: 0.75rem;
    }
    .search-group .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
        height: 36px;
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
    .service-requests-table .table thead th,
    .service-requests-table .table tbody td {
        white-space: nowrap;
        vertical-align: middle;
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
    .service-requests-table .fixed-table-toolbar {
        margin-bottom: 0.75rem;
    }
    .service-requests-table .fixed-table-toolbar .columns {
        display: inline-flex;
        align-items: center;
        gap: 0.15rem;
        background: #4b5563;
        border-radius: 0.75rem;
        padding: 0.25rem;
        box-shadow: 0 10px 18px rgba(15, 23, 42, 0.18);
    }
    .service-requests-table .fixed-table-toolbar .columns .btn,
    .service-requests-table .fixed-table-toolbar .columns .btn-group > .btn {
        background: transparent;
        border: 0;
        color: #ffffff;
        width: 36px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: none;
    }
    .service-requests-table .fixed-table-toolbar .columns .btn:hover,
    .service-requests-table .fixed-table-toolbar .columns .btn-group > .btn:hover {
        background: rgba(255, 255, 255, 0.12);
    }
    .service-requests-table .fixed-table-toolbar .columns .dropdown-toggle::after {
        display: none;
    }
    .service-requests-table .fixed-table-toolbar .columns .btn i {
        font-size: 1rem;
    }
    .service-requests-table .table-square-thumb {
        width: 56px;
        height: 56px;
        object-fit: cover;
        border-radius: 0.6rem;
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
        .search-group .form-select,
        .search-group .select2-container {
            width: 100%;
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
            $totalItems = (int) ($stats['total'] ?? 0);
            $reviewItems = (int) ($stats['review'] ?? 0);
            $selectedCategoryName = $selectedCategory ? $selectedCategory->name : __('All Categories');
        @endphp
        <div class="service-requests-shell">
            <div class="service-requests-hero">
                <div>
                    <h5 class="service-requests-title">@yield('title')</h5>
                    <p class="service-requests-subtitle">{{ __('Track Shein products, approvals, and inventory status.') }}</p>
                </div>
                <div class="service-requests-metrics">
                    <div class="metric-card category">
                        <span class="metric-icon"><i class="bi bi-folder2-open"></i></span>
                        <div>
                            <div class="metric-label">{{ __('Category') }}</div>
                            <div class="metric-value text-truncate" title="{{ $selectedCategoryName }}">
                                {{ $selectedCategoryName }}
                            </div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <span class="metric-icon"><i class="bi bi-collection"></i></span>
                        <div>
                            <div class="metric-label">{{ __('Total products') }}</div>
                            <div class="metric-value">{{ number_format($totalItems) }}</div>
                        </div>
                    </div>
                    <div class="metric-card review">
                        <span class="metric-icon"><i class="bi bi-hourglass-split"></i></span>
                        <div>
                            <div class="metric-label">{{ __('Under Review') }}</div>
                            <div class="metric-value">{{ number_format($reviewItems) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card service-requests-filters">
                <div class="card-body">
                    <div class="filters-header">
                        <div>
                            <h6 class="filters-title">{{ __('Filter') }}</h6>
                            <p class="filters-hint">{{ __('Narrow down by status or category before exporting data.') }}</p>
                        </div>
                    </div>
                    <div class="filters-row">
                        <div class="status-tabs" role="tablist">
                            <button type="button" class="status-tab active" data-status="">{{ __('All') }}</button>
                            <button type="button" class="status-tab" data-status="review">{{ __('Under Review') }}</button>
                            <button type="button" class="status-tab" data-status="approved">{{ __('Approved') }}</button>
                            <button type="button" class="status-tab" data-status="rejected">{{ __('Rejected') }}</button>
                        </div>
                        <input type="hidden" id="status_filter" value="">
                        <div class="search-group">
                            <select class="form-select" id="category_filter" name="category_filter">
                                <option value="">{{ __('All Categories') }}</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ $category->id == 4 ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="item_search" placeholder="{{ __('Search by name or ID') }}" autocomplete="off">
                            </div>
                            <button class="btn btn-primary" type="button" id="filtersApply">{{ __('Search') }}</button>
                            <button class="btn btn-outline-secondary" type="button" id="filtersReset">{{ __('Reset') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card service-requests-table">
                <div class="card-header">
                    <div>
                        <h6 class="table-title">{{ __('Shein product list') }}</h6>
                        <p class="table-hint">{{ __('Use the toolbar to export, refresh, or customize columns.') }}</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table
                           class="table table-striped table-hover align-middle"
                           aria-describedby="sheinProductsTableCaption"
                           id="table_list"
                           data-toggle="table"
                           data-url="{{ route('item.shein.products.data') }}"
                           data-click-to-select="true"
                           data-side-pagination="server"
                           data-pagination="true"
                           data-page-list="[5, 10, 20, 50, 100, 200]"
                           data-search="false"
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
                           data-export-options='{"fileName": "shein-item-list","ignoreColumn": ["operate"]}'
                           data-export-types='["pdf","json","xml","csv","txt","sql","doc","excel"]'
                           data-icons="serviceRequestsTableIcons"
                           data-icons-prefix="bi"
                           data-mobile-responsive="true"
                           data-query-params="queryParams">
                            <thead>
                            <tr>
                                <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                <th scope="col" data-field="name" data-sortable="true">{{ __('Name') }}</th>
                                <th scope="col" data-field="description" data-align="center" data-sortable="true" data-formatter="descriptionFormatter">{{ __('Description') }}</th>
                                <th scope="col" data-field="user.name" data-sort-name="user_name" data-sortable="true">{{ __('User') }}</th>
                                <th scope="col" data-field="price" data-sortable="true">{{ __('Price') }}</th>
                                <th scope="col" data-field="currency" data-sortable="true">{{ __('Currency') }}</th>
                                <th scope="col" data-field="image" data-sortable="false" data-escape="false" data-formatter="imageFormatter">{{ __('Image') }}</th>
                                <th scope="col" data-field="gallery_images" data-sortable="false" data-formatter="galleryImageFormatter" data-escape="false" data-visible="false">{{ __('Other Images') }}</th>
                                <th scope="col" data-field="latitude" data-sortable="true" data-visible="false">{{ __('Latitude') }}</th>
                                <th scope="col" data-field="longitude" data-sortable="true" data-visible="false">{{ __('Longitude') }}</th>
                                <th scope="col" data-field="address" data-sortable="true" data-visible="false">{{ __('Address') }}</th>
                                <th scope="col" data-field="contact" data-sortable="true" data-visible="false">{{ __('Contact') }}</th>
                                <th scope="col" data-field="country" data-sortable="true" data-visible="false">{{ __('Country') }}</th>
                                <th scope="col" data-field="state" data-sortable="true" data-visible="false">{{ __('State') }}</th>
                                <th scope="col" data-field="city" data-sortable="true" data-visible="false">{{ __('City') }}</th>
                                <th scope="col" data-field="status" data-sortable="true" data-filter-control="select" data-filter-data="" data-escape="false" data-formatter="itemStatusFormatter" data-visible="false">{{ __('Status') }}</th>
                                @can('item-update')
                                    <th scope="col" data-field="active_status" data-sortable="true" data-sort-name="deleted_at" data-visible="false" data-escape="false" data-formatter="statusSwitchFormatter">{{ __('Active') }}</th>
                                @endcan
                                <th scope="col" data-field="rejected_reason" data-sortable="true" data-visible="false">{{ __('Rejected Reason') }}</th>
                                <th scope="col" data-field="created_at" data-sortable="true" data-visible="false">{{ __('Created At') }}</th>
                                <th scope="col" data-field="updated_at" data-sortable="true" data-visible="false">{{ __('Updated At') }}</th>
                                <th scope="col" data-field="user_id" data-sortable="true" data-visible="false">{{ __('User ID') }}</th>
                                <th scope="col" data-field="category_id" data-sortable="true" data-visible="false">{{ __('Category ID') }}</th>
                                <th scope="col" data-field="likes" data-sortable="true" data-visible="false">{{ __('Likes') }}</th>
                                <th scope="col" data-field="clicks" data-sortable="true" data-visible="false">{{ __('Clicks') }}</th>
                                @canany(['shein-products-update','shein-products-delete'])
                                    <th scope="col" data-field="operate" data-align="center" data-sortable="false" data-events="itemEvents" data-escape="false">{{ __('Action') }}</th>
                                @endcanany
                            </tr>
                            </thead>
                        </table>
                    </div>
                    <div id="sheinProductsTableCaption" class="visually-hidden">{{ __('Use the toolbar to export, refresh, or customize columns.') }}</div>
                </div>
            </div>
        </div>
        <div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1"
             aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="myModalLabel1">{{ __('Item Details') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="center" id="custom_fields"></div>
                    </div>
                </div>
            </div>
            <!-- /.modal-content -->
        </div>
        <div id="editStatusModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1"
             aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="myModalLabel1">{{ __('Status') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form class="edit-form" action="" method="POST" data-success-function="updateApprovalSuccess">
                            @csrf
                            <div class="row">
                                <div class="col-md-12">
                                    <select name="status" class="form-select" id="status" aria-label="status">
                                        <option value="review">{{ __('Under Review') }}</option>
                                        <option value="approved">{{ __('Approve') }}</option>
                                        <option value="rejected">{{ __('Reject') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div id="rejected_reason_container" class="col-md-12" style="display: none;">
                                <label for="rejected_reason" class="mandatory form-label">{{ __('Reason') }}</label>
                                <textarea name="rejected_reason" id="rejected_reason" class="form-control" placeholder={{ __('Reason') }}></textarea>
                            </div>
                            <input type="submit" value="{{ __('Save') }}" class="btn btn-primary mt-3">
                        </form>
                    </div>
                </div>
            </div>
            <!-- /.modal-content -->
        </div>
    </section>
@endsection

@section('script')
    <script>
        window.serviceRequestsTableIcons = {
            refresh: 'bi-arrow-clockwise',
            columns: 'bi-list-ul',
            export: 'bi-download'
        };

        function updateApprovalSuccess() {
            $('#editStatusModal').modal('hide');
        }

        const CATEGORY_ROOT_ID = 4;

        function itemEvents() {
            return {
                'click .editdata': function (e, value, row, index) {
                    var html = '';
                    $.each(row.custom_fields, function (key, val) {
                        html += '<div class="form-group">';
                        html += '<label>' + val.name + '</label>';
                        if (val.type === 'textarea') {
                            html += '<textarea class="form-control" readonly>' + (val.value ? val.value.value : '') + '</textarea>';
                        } else if (val.type === 'fileinput') {
                            if (val.value && val.value.value) {
                                html += '<div><img src="' + val.value.value + '" class="img-thumbnail" style="max-width:200px"></div>';
                            } else {
                                html += '<div>No Image</div>';
                            }
                        } else {
                            html += '<input type="text" class="form-control" value="' + (val.value ? val.value.value : '') + '" readonly>';
                        }
                        html += '</div>';
                    });
                    $('#custom_fields').html(html);
                },
                'click .edit-status': function (e, value, row, index) {
                    window.location.href = row.edit_url;
                },
                'click .edit-item': function (e, value, row, index) {
                    window.location.href = value;
                }
            };
        }

        function itemStatusFormatter(value, row) {
            let status = '';
            if (value === 'review') {
                status = '<span class="badge bg-warning">' + '{{ __('Under Review') }}' + '</span>';
            } else if (value === 'approved') {
                status = '<span class="badge bg-success">' + '{{ __('Approved') }}' + '</span>';
            } else if (value === 'rejected') {
                status = '<span class="badge bg-danger">' + '{{ __('Rejected') }}' + '</span>';
            } else if (value === 'sold out') {
                status = '<span class="badge bg-secondary">' + '{{ __('Sold Out') }}' + '</span>';
            }
            return status;
        }

        function statusSwitchFormatter(value, row) {
            let checked = value ? 'checked' : '';
            return '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" ' + checked + ' disabled></div>';
        }

        function imageFormatter(value, row) {
            if (value) {
                return '<img src="' + value + '" class="img-thumbnail table-square-thumb" alt="image">';
            }
            return '';
        }

        function galleryImageFormatter(value, row) {
            if (value && value.length > 0) {
                let html = '<div class="d-flex flex-wrap">';
                for (let i = 0; i < value.length; i++) {
                    html += '<img src="' + value[i].image + '" class="img-thumbnail table-square-thumb me-1 mb-1" alt="gallery">';
                }
                html += '</div>';
                return html;
            }
            return '';
        }

        function queryParams(params) {
            const query = {
                section: 'shein',
                category_root: CATEGORY_ROOT_ID,
                offset: params.offset,
                limit: params.limit,
                search: params.search,
                sort: params.sort,
                order: params.order,
                filter: params.filter
            };

            const status = ($('#status_filter').val() || '').trim();
            if (status) {
                query.status = status;
            }

            const selectedCategory = $('#category_filter').val();
            if (selectedCategory) {
                query.category_id = selectedCategory;
            } else {
                query.category_id = CATEGORY_ROOT_ID;
            }

            const searchTerm = ($('#item_search').val() || '').trim();
            if (searchTerm) {
                query.search = searchTerm;
            }

            return query;
        }

        $(document).ready(function() {
            const $table = $('#table_list');
            const $searchInput = $('#item_search');
            const $statusFilter = $('#status_filter');
            const $categoryFilter = $('#category_filter');

            $categoryFilter.select2({
                theme: 'bootstrap-5',
                placeholder: "{{ __('All Categories') }}",
                allowClear: true,
                width: 'style'
            });

            $categoryFilter.val(String(CATEGORY_ROOT_ID)).trigger('change');

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

            $('#filtersApply').on('click', function () {
                refreshTableToFirstPage();
            });

            $('#filtersReset').on('click', function () {
                $searchInput.val('');
                $categoryFilter.val(String(CATEGORY_ROOT_ID)).trigger('change');
                $statusFilter.val('');
                $('.status-tab').removeClass('active');
                $('.status-tab[data-status=""]').addClass('active');
                refreshTableToFirstPage();
            });

            $searchInput.on('keypress', function (event) {
                if (event.which === 13) {
                    event.preventDefault();
                    refreshTableToFirstPage();
                }
            });

            $categoryFilter.on('change', function () {
                refreshTableToFirstPage();
            });

            $('#status').on('change', function() {
                $('#rejected_reason_container').toggle($(this).val() === 'rejected');
            });
        });
    </script>
@endsection
