@extends('layouts.main')

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>{{ __('Shein Items') }}</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first"></div>
        </div>
    </div>
@endsection

@section('page-style')
<style>
    .shein-products-page {
        background: linear-gradient(180deg, rgba(13, 110, 253, 0.07), rgba(13, 110, 253, 0.02));
        border: 1px solid rgba(15, 23, 42, 0.06);
        border-radius: 1.25rem;
        color: #212529;
        padding: 1.25rem;
    }
    .shein-products-shell {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .shein-products-hero {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .shein-products-title {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 700;
        color: #0f172a;
    }
    .shein-products-subtitle {
        margin: 0.35rem 0 0;
        font-size: 0.9rem;
        color: #6c757d;
    }
    .shein-products-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .shein-products-filters {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 1rem;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
    }
    .shein-products-filters .card-body {
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
    .shein-products-table {
        border-radius: 1rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        margin-bottom: 0;
        overflow: hidden;
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
    }
    .shein-products-table .card-header {
        background: #f8f9fb;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        padding: 0.9rem 1.1rem;
    }
    .shein-products-table .card-body {
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
    .shein-products-table .table {
        margin-bottom: 0;
    }
    .shein-products-table .table thead th {
        background: #f8f9fa;
        color: #212529;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        padding: 0.85rem 1rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .shein-products-table .table tbody td {
        padding: 0.85rem 1rem;
    }
    .shein-products-table .table tbody tr {
        transition: background-color 0.2s ease;
    }
    .shein-products-table .table tbody tr:hover {
        background-color: rgba(13, 110, 253, 0.04);
    }
    .shein-products-table .table-striped > tbody > tr:nth-of-type(odd) {
        background-color: rgba(15, 23, 42, 0.02);
    }
    .shein-products-table .fixed-table-toolbar {
        margin-bottom: 0.75rem;
    }
    .shein-products-table .fixed-table-toolbar .columns {
        display: inline-flex;
        align-items: center;
        gap: 0.15rem;
        background: #4b5563;
        border-radius: 0.75rem;
        padding: 0.25rem;
        box-shadow: 0 10px 18px rgba(15, 23, 42, 0.18);
    }
    .shein-products-table .fixed-table-toolbar .columns .btn,
    .shein-products-table .fixed-table-toolbar .columns .btn-group > .btn {
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
    .shein-products-table .fixed-table-toolbar .columns .btn:hover,
    .shein-products-table .fixed-table-toolbar .columns .btn-group > .btn:hover {
        background: rgba(255, 255, 255, 0.12);
    }
    .shein-products-table .fixed-table-toolbar .columns .dropdown-toggle::after {
        display: none;
    }
    .shein-products-table .fixed-table-toolbar .columns .btn i {
        font-size: 1rem;
    }
    .shein-products-page .card-body {
        overflow-x: hidden;
    }
    
    /* Reset default Select2 width */
    .select2-container {
        width: 100% !important;
    }
    
    /* Target category filter specifically with highest specificity */
    #filters .col-lg-8 .select2-container,
    #filters .col-lg-8 .select2-container.select2-container--default,
    #filters .col-lg-8 .select2-container.select2-container--open,
    #category_filter + .select2-container {
        width: 100% !important;
        min-width: 400px !important;
    }
    
    /* Force width on dropdown */
    .select2-dropdown,
    .select2-dropdown.select2-dropdown--below,
    .select2-dropdown.select2-dropdown--above {
        width: auto !important;
        min-width: 400px !important;
    }
    
    /* Custom class for our dropdown */
    .category-filter-dropdown {
        width: 100% !important;
        min-width: 400px !important;
    }
    
    /* Force width on selection container */
    .select2-container .select2-selection {
        width: 100% !important;
        min-width: 400px !important;
    }

    #table_list {
        width: 100%;
    }

    @media (min-width: 992px) {
        .shein-products-hero {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }
    }

    @media (max-width: 768px) {
        .shein-products-page {
            padding: 1rem;
        }
        .select2-container .select2-selection,
        #category_filter + .select2-container {
            min-width: 0 !important;
        }
        .select2-dropdown,
        .select2-dropdown.select2-dropdown--below,
        .select2-dropdown.select2-dropdown--above {
            min-width: 0 !important;
        }
    }
</style>
@endsection

@section('content')
    <section class="section shein-products-page">
        <div class="shein-products-shell">
            <div class="shein-products-hero">
                <div>
                    <h5 class="shein-products-title">{{ __('Shein Items') }}</h5>
                    <p class="shein-products-subtitle">{{ __('Track Shein products, approvals, and inventory status.') }}</p>
                </div>
                <div class="shein-products-actions">
                    @can('shein-products-create')
                        <a href="{{ route('item.shein.products.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> {{ __('Add New Item') }}
                        </a>
                    @endcan
                </div>
            </div>

            <div class="card shein-products-filters">
                <div class="card-body">
                    <div class="filters-header">
                        <div>
                            <h6 class="filters-title">{{ __('Filter') }}</h6>
                            <p class="filters-hint">{{ __('Narrow down by status or category before exporting data.') }}</p>
                        </div>
                    </div>
                    <div id="filters" class="row g-3 align-items-end">
                        <div class="col-12 col-lg-4">
                            <label for="filter" class="form-label">{{__("Status")}}</label>
                            <select class="form-control bootstrap-table-filter-control-status" id="filter">
                                <option value="">{{__("All")}}</option>
                                <option value="review">{{__("Under Review")}}</option>
                                <option value="approved">{{__("Approved")}}</option>
                                <option value="rejected">{{__("Rejected")}}</option>
                                <option value="sold out">{{__("Sold Out")}}</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-8">
                            <label for="category_filter" class="form-label">{{__("Category")}}</label>
                            <select class="form-control select2" id="category_filter" name="category_filter">
                                <option value="">{{__("All Categories")}}</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ $category->id == 4 ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shein-products-table">
                <div class="card-header">
                    <div>
                        <h6 class="table-title">{{ __('Shein product list') }}</h6>
                        <p class="table-hint">{{ __('Use the toolbar to export, refresh, or customize columns.') }}</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle" aria-describedby="mydesc" id="table_list"
                               data-toggle="table" data-url="{{ route('item.shein.products.data') }}" data-click-to-select="true"
                               data-side-pagination="server" data-pagination="true"
                               data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                               data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                               data-fixed-number="1" data-fixed-right-number="1" data-trim-on-search="false"
                               data-escape="true"
                               data-responsive="true" data-sort-name="id" data-sort-order="desc"
                               data-pagination-successively-size="3" data-table="items" data-status-column="deleted_at"
                               data-show-export="true" data-export-options='{"fileName": "shein-item-list","ignoreColumn": ["operate"]}' data-export-types="['pdf','json', 'xml', 'csv', 'txt', 'sql', 'doc', 'excel']"
                               data-mobile-responsive="true" data-filter-control="true" data-filter-control-container="#filters" data-toolbar="#filters">
                            <thead class="thead-dark">
                            <tr>
                                <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                <th scope="col" data-field="name" data-sortable="true">{{ __('Name') }}</th>
                                <th scope="col" data-field="description" data-align="center" data-sortable="true" data-formatter="descriptionFormatter">{{ __('Description') }}</th>
                                <th scope="col" data-field="user.name" data-sort-name="user_name" data-sortable="true">{{ __('User') }}</th>
                                <th scope="col" data-field="price" data-sortable="true">{{ __('Price') }}</th>
                                <th scope="col" data-field="currency" data-sortable="true">{{ __('Currency') }}</th>
                                <th scope="col" data-field="image" data-sortable="false" data-escape="false" data-formatter="imageFormatter">{{ __('Image') }}</th>
                                <th scope="col" data-field="gallery_images" data-sortable="false" data-formatter="galleryImageFormatter" data-escape="false">{{ __('Other Images') }}</th>
                                <th scope="col" data-field="latitude" data-sortable="true" data-visible="false">{{ __('Latitude') }}</th>
                                <th scope="col" data-field="longitude" data-sortable="true" data-visible="false">{{ __('Longitude') }}</th>
                                <th scope="col" data-field="address" data-sortable="true" data-visible="false">{{ __('Address') }}</th>
                                <th scope="col" data-field="contact" data-sortable="true" data-visible="false">{{ __('Contact') }}</th>
                                <th scope="col" data-field="country" data-sortable="true" data-visible="true">{{ __('Country') }}</th>
                                <th scope="col" data-field="state" data-sortable="true" data-visible="true">{{ __('State') }}</th>
                                <th scope="col" data-field="city" data-sortable="true" data-visible="true">{{ __('City') }}</th>
                                <th scope="col" data-field="status" data-sortable="true" data-filter-control="select" data-filter-data="" data-escape="false" data-formatter="itemStatusFormatter">{{ __('Status') }}</th>
                                @can('item-update')
                                    <th scope="col" data-field="active_status" data-sortable="true" data-sort-name="deleted_at" data-visible="true" data-escape="false" data-formatter="statusSwitchFormatter">{{ __('Active') }}</th>
                                @endcan
                                <th scope="col" data-field="rejected_reason" data-sortable="true" data-visible="true">{{ __('Rejected Reason') }}</th>
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
                                        <option value="review">{{__("Under Review")}}</option>
                                        <option value="approved">{{__("Approve")}}</option>
                                        <option value="rejected">{{__("Reject")}}</option>
                                    </select>
                                </div>
                            </div>
                            <div id="rejected_reason_container" class="col-md-12" style="display: none;">
                                <label for="rejected_reason" class="mandatory form-label">{{ __('Reason') }}</label>
                                <textarea name="rejected_reason" id="rejected_reason" class="form-control" placeholder={{ __('Reason') }}></textarea>
                                {{-- <input type="text" name="rejected_reason" id="rejected_reason" class="form-control"> --}}
                            </div>
                            <input type="submit" value="{{__("Save")}}" class="btn btn-primary mt-3">
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
        function updateApprovalSuccess() {
            $('#editStatusModal').modal('hide');
        }

        function forceSelect2Width() {
            // Force the width of all Select2 elements
            $('.select2-container').css('width', '100%');
            
            // Specifically target category filter
            var $categorySelect = $('#category_filter');
            var $categoryContainer = $categorySelect.next('.select2-container');
            
            $categoryContainer.css({
                'width': '100%',
                'min-width': '400px'
            });
            
            $categoryContainer.find('.select2-selection').css({
                'width': '100%',
                'min-width': '400px'
            });
        }

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
                    // Redirect to edit page instead of showing status modal
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
                status = '<span class="badge bg-warning">' + '{{ __("Under Review") }}' + '</span>';
            } else if (value === 'approved') {
                status = '<span class="badge bg-success">' + '{{ __("Approved") }}' + '</span>';
            } else if (value === 'rejected') {
                status = '<span class="badge bg-danger">' + '{{ __("Rejected") }}' + '</span>';
            } else if (value === 'sold out') {
                status = '<span class="badge bg-secondary">' + '{{ __("Sold Out") }}' + '</span>';
            }
            return status;
        }

        function statusSwitchFormatter(value, row) {
            let checked = value ? 'checked' : '';
            return '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" ' + checked + ' disabled></div>';
        }

        function imageFormatter(value, row) {
            if (value) {
                return '<img src="' + value + '" class="img-thumbnail" style="max-width:100px">';
            }
            return '';
        }

        function galleryImageFormatter(value, row) {
            if (value && value.length > 0) {
                let html = '<div class="d-flex flex-wrap">';
                for (let i = 0; i < value.length; i++) {
                    html += '<img src="' + value[i].image + '" class="img-thumbnail me-1 mb-1" style="max-width:50px; max-height:50px;">';
                }
                html += '</div>';
                return html;
            }
            return '';
        }

        function sheinQueryParams(params) {
            params = params || {};

            params.section = 'shein';
            params.category_root = 4;

            var selectedCategory = $('#category_filter').val();
            if (selectedCategory && selectedCategory !== '' && selectedCategory !== '4') {
                params.category_id = selectedCategory;
            } else {
                delete params.category_id;
            }

            var statusFilter = $('#filter').val();
            if (statusFilter) {
                params.status = statusFilter;
            } else {
                delete params.status;
            }

            return params;
        }


        $(document).ready(function() {
            // Initialize Select2 with explicit width
            $('#category_filter').select2({
                placeholder: "{{__("Search Categories")}}",
                allowClear: true,
                width: '100%',
                dropdownCssClass: 'category-filter-dropdown'
            });

            // Set default category filter value for Shein page to category ID 4
            $('#category_filter').val(4).trigger('change');

            // Override queryParams to always include category_id=4
            var $table = $('#table_list');
            
            $table.bootstrapTable({
                queryParams: sheinQueryParams

            });

            // Force width immediately after initialization
            forceSelect2Width();
            
            // Force width again after a short delay
            setTimeout(forceSelect2Width, 100);
            
            // Force width periodically to handle any dynamic changes
            setInterval(forceSelect2Width, 500);
            
            // Force width when dropdown opens
            $('#category_filter').on('select2:open', function() {
                forceSelect2Width();
                $('.select2-dropdown').css({
                    'width': '100%',
                    'min-width': '400px'
                });
            });

            // Update filters and refresh table when category changes

            function refreshSheinTable() {
                $table.bootstrapTable('refresh', {
                    query: {
                        section: 'shein',
                        category_root: 4
                    }
                });
            }


            $('#category_filter').on('change', function() {
                refreshSheinTable();
                forceSelect2Width();
            });

            // Update filters and refresh table when status changes
            $('#filter').on('change', function() {
                refreshSheinTable();
            });

            $('#status').on('change', function() {
                if ($(this).val() == 'rejected') {
                    $('#rejected_reason_container').show();
                    $('#rejected_reason').attr('required', true);
                } else {
                    $('#rejected_reason_container').hide();
                    $('#rejected_reason').attr('required', false);
                }
            });
        });
    </script>
@endsection
