@extends('layouts.main')

@section('page-title')
    {{ __('Add New Shein Item') }}
@endsection

@section('content')
    <section class="section">
        <div class="card">
            <div class="card-body">
                @php
                    $sizeCatalogList = $sizeCatalog ?? [];
                    $colorRows = old('colors');
                    if (! is_array($colorRows)) {
                        $colorRows = [];
                    } else {
                        $colorRows = array_values($colorRows);
                    }
                    $selectedColors = collect($colorRows)
                        ->map(function ($row) {
                            if (! is_array($row)) {
                                return null;
                            }
                            $code = trim((string) ($row['code'] ?? ''));
                            if ($code === '') {
                                return null;
                            }
                            $code = strtoupper($code);
                            if ($code[0] !== '#') {
                                $code = '#' . ltrim($code, '#');
                            }
                            $label = trim((string) ($row['label'] ?? ''));

                            return [
                                'code' => $code,
                                'label' => $label,
                            ];
                        })
                        ->filter()
                        ->unique('code')
                        ->values()
                        ->all();

                    $sizeRows = old('sizes');
                    if (! is_array($sizeRows)) {
                        $sizeRows = [];
                    } else {
                        $sizeRows = array_values($sizeRows);
                    }
                    $sizeCatalogValues = collect($sizeCatalogList)
                        ->map(static fn ($entry) => (string) $entry)
                        ->values()
                        ->all();
                    $selectedSizes = collect($sizeRows)
                        ->map(function ($row) {
                            if (is_array($row) && array_key_exists('value', $row)) {
                                $row = $row['value'];
                            }
                            return trim((string) $row);
                        })
                        ->filter(static fn ($value) => $value !== '')
                        ->unique()
                        ->values()
                        ->all();
                    $customRows = old('custom_options');
                    if (! is_array($customRows) || $customRows === []) {
                        $customRows = [''];
                    } else {
                        $customRows = array_values($customRows);
                    }
                    $variantStocks = old('variant_stocks');
                    if (! is_array($variantStocks)) {
                        $variantStocks = [];
                    }
                @endphp
                <form id="createSheinForm" action="{{ route('item.shein.products.store') }}" method="POST" enctype="multipart/form-data" data-success-function="formSuccessFunction">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="name" class="form-label mandatory">{{ __('Name') }}</label>
                                <input type="text" id="name" name="name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="category_id" class="form-label mandatory">{{ __('Category') }}</label>
                                <select id="category_id" name="category_id" class="form-control select2" required>
                                    <option value="">{{ __('Select Category') }}</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category['id'] }}"
                                                data-icon="{{ $category['icon'] ?? '' }}"
                                                {{ old('category_id', $selectedCategoryId ?? request('category_id')) == $category['id'] ? 'selected' : '' }}>
                                            {{ $category['label'] ?? $category['name'] ?? '' }}
                                        </option>

                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label for="price" class="form-label mandatory">{{ __('Price') }}</label>
                                <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label for="currency" class="form-label mandatory">{{ __('Currency') }}</label>
                                <select id="currency" name="currency" class="form-control" required>
                                    <option value="USD">USD</option>
                                    <option value="YER">YER</option>
                                    <option value="SAR">SAR</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label for="stock" class="form-label">{{ __('merchant_products.form.stock') }}</label>
                                <input type="number" id="stock" name="stock" class="form-control" min="0" value="{{ old('stock', 0) }}">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label for="discount_type" class="form-label">{{ __('merchant_products.form.discount_type') }}</label>
                                <select id="discount_type" name="discount_type" class="form-control">
                                    <option value="none" @selected(old('discount_type', 'none') === 'none')>{{ __('merchant_products.form.discount_none') }}</option>
                                    <option value="percentage" @selected(old('discount_type') === 'percentage')>{{ __('merchant_products.form.discount_percentage') }}</option>
                                    <option value="fixed" @selected(old('discount_type') === 'fixed')>{{ __('merchant_products.form.discount_fixed') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label for="discount_value" class="form-label">{{ __('merchant_products.form.discount_value') }}</label>
                                <input type="number" id="discount_value" name="discount_value" class="form-control" min="0" step="0.01" value="{{ old('discount_value') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label class="form-label">{{ __('merchant_products.form.discount_schedule') }}</label>
                                <div class="input-group">
                                    <input type="datetime-local" name="discount_start" class="form-control" value="{{ old('discount_start') }}">
                                    <input type="datetime-local" name="discount_end" class="form-control" value="{{ old('discount_end') }}">
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class="col-md-6">

                            <div class="form-group mb-3">
                                <label for="product_link" class="form-label mandatory">{{ __('Product Link') }}</label>
                                <div class="input-group">
                                    <input type="url" id="product_link" name="product_link" class="form-control @error('product_link') is-invalid @enderror" value="{{ old('product_link') }}" required maxlength="2048">
                                    <button type="button" class="btn btn-outline-primary" id="shein_import_btn" data-import-url="{{ route('item.shein.products.import') }}">{{ __('Fetch Shein Data') }}</button>
                                </div>
                                @error('product_link')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small id="shein_import_status" class="form-text text-muted"></small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="review_link" class="form-label">{{ __('Review Link') }}</label>
                                <input type="url" id="review_link" name="review_link" class="form-control @error('review_link') is-invalid @enderror" value="{{ old('review_link') }}" maxlength="2048">
                                @error('review_link')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">{{ __('Provide a trusted external review URL (optional).') }}</small>
                            </div>
                        </div>

                    </div>



                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="video_link" class="form-label">{{ __('merchant_products.form.video_link') }}</label>
                                <input type="url" id="video_link" name="video_link" class="form-control" value="{{ old('video_link') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="delivery_size" class="form-label">{{ __('merchant_products.form.delivery_size') }}</label>
                                <input type="number" id="delivery_size" name="delivery_size" class="form-control" min="0" step="0.01" value="{{ old('delivery_size') }}">
                                <small class="form-text text-muted">{{ __('merchant_products.form.delivery_size_help') }}</small>
                            </div>
                        </div>
                    </div>

                    @if(isset($categoryIcons) && $categoryIcons->isNotEmpty())
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-3">
                                    @foreach($categoryIcons as $categoryId => $icon)
                                        <div class="text-center">
                                            <img src="{{ $icon }}" alt="{{ $categories->firstWhere('id', $categoryId)['name'] ?? '' }}"
                                                 class="img-fluid rounded"
                                                 style="max-width: 70px; max-height: 70px; object-fit: contain;">
                                            <div class="small mt-2 text-muted">
                                                {{ $categories->firstWhere('id', $categoryId)['name'] ?? '' }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif


                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group mb-3">
                                <label for="description" class="form-label mandatory">{{ __('Description') }}</label>
                                <textarea id="description" name="description" class="form-control" rows="4" required></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="country" class="form-label">{{ __('Country') }}</label>
                                <input type="text" id="country" name="country" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="state" class="form-label">{{ __('State') }}</label>
                                <input type="text" id="state" name="state" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="city" class="form-label">{{ __('City') }}</label>
                                <input type="text" id="city" name="city" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="contact" class="form-label">{{ __('Contact') }}</label>
                                <input type="text" id="contact" name="contact" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group mb-3">
                                <label for="address" class="form-label">{{ __('Address') }}</label>
                                <textarea id="address" name="address" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="image" class="form-label mandatory">{{ __('Main Image') }}</label>
                                <input type="file" id="image" name="image" class="form-control" accept="image/*" required>
                                <div class="mt-2">
                                    <img id="image_preview" src="" alt="" style="max-width: 200px; display: none;">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="gallery_images" class="form-label">{{ __('Gallery Images') }}</label>
                                <input type="file" id="gallery_images" name="gallery_images[]" class="form-control" accept="image/*" multiple>
                                <div id="gallery_preview" class="mt-2 d-flex flex-wrap"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="form-group mb-3">
                                <label class="form-label">{{ __('Imported Images') }}</label>
                                <div id="imported_images_preview" class="d-flex flex-wrap gap-2"></div>
                                <div id="imported_images_inputs"></div>
                                <small class="form-text text-muted">{{ __('Imported images are used for the main image (first) and gallery (rest).') }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5>{{ __('merchant_products.form.attributes_title') }}</h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-3">{{ __('merchant_products.form.attributes_description') }}</p>

                                    <div class="border rounded p-3 mb-4">
                                        <div class="row g-3">
                                            <div class="col-lg-7">
                                                <label for="shein_color_select" class="form-label">{{ __('merchant_products.form.colors_title') }}</label>
                                                <select id="shein_color_select" class="form-control select2" multiple data-color-select>
                                                    @foreach ($selectedColors as $color)
                                                        @php
                                                            $colorCode = $color['code'] ?? '';
                                                            $colorLabel = $color['label'] ?? '';
                                                            $colorText = $colorLabel !== '' ? $colorLabel . ' (' . $colorCode . ')' : $colorCode;
                                                        @endphp
                                                        <option value="{{ $colorCode }}" data-label="{{ $colorLabel }}" selected>{{ $colorText }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-lg-5">
                                                <label class="form-label">{{ __('merchant_products.form.add_color') }}</label>
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-4 col-md-3">
                                                        <label class="form-label small text-muted">{{ __('merchant_products.form.color_picker') }}</label>
                                                        <input type="color" class="form-control form-control-color" value="#ffffff" data-custom-color-code>
                                                    </div>
                                                    <div class="col-8 col-md-6">
                                                        <label class="form-label small text-muted">{{ __('merchant_products.form.color_label') }}</label>
                                                        <input type="text" class="form-control" placeholder="{{ __('merchant_products.form.color_label_placeholder') }}" data-custom-color-label>
                                                    </div>
                                                    <div class="col-12 col-md-3 d-grid">
                                                        <label class="form-label small text-muted d-none d-md-block">&nbsp;</label>
                                                        <button type="button" class="btn btn-outline-primary btn-sm" data-add-custom-color>
                                                            <i class="bi bi-plus-lg"></i>
                                                            {{ __('merchant_products.form.add_color') }}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-none" data-color-inputs></div>
                                    </div>

                                    <div class="border rounded p-3 mb-4">
                                        <label for="shein_size_select" class="form-label">{{ __('merchant_products.form.sizes_title') }}</label>
                                        <select id="shein_size_select" class="form-control select2" multiple data-size-select>
                                            @foreach ($sizeCatalogList as $option)
                                                @php
                                                    $optionValue = (string) $option;
                                                @endphp
                                                <option value="{{ $optionValue }}" @selected(in_array($optionValue, $selectedSizes, true))>{{ $optionValue }}</option>
                                            @endforeach
                                            @foreach ($selectedSizes as $sizeValue)
                                                @if (! in_array($sizeValue, $sizeCatalogValues, true))
                                                    <option value="{{ $sizeValue }}" selected>{{ $sizeValue }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <small class="text-muted">{{ __('merchant_products.form.size_catalog_hint') }}</small>
                                        <div class="d-none" data-size-inputs></div>
                                    </div>

                                    <div class="border rounded p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="mb-0">{{ __('merchant_products.form.custom_title') }}</h6>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-add-option>
                                                <i class="bi bi-plus-lg"></i>
                                                {{ __('merchant_products.form.add_option') }}
                                            </button>
                                        </div>
                                        <div class="d-flex flex-column gap-3" data-option-rows>
                                            @foreach ($customRows as $index => $value)
                                                <div class="row g-3 align-items-end" data-repeater-row>
                                                    <div class="col-md-10">
                                                        <input type="text"
                                                               name="custom_options[{{ $index }}]"
                                                               class="form-control"
                                                               placeholder="{{ __('merchant_products.form.custom_placeholder') }}"
                                                               value="{{ old('custom_options.' . $index, $value) }}">
                                                    </div>
                                                    <div class="col-md-2 text-end">
                                                        <button type="button" class="btn btn-outline-danger btn-sm" data-remove-row>
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">{{ __('merchant_products.inventory.title') }}</h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-3">
                                        {{ __('Set quantities for each size within every selected color.') }}
                                    </p>
                                    <div data-variant-grid></div>
                                    <div class="text-muted small" data-variant-empty>
                                        {{ __('Select colors and sizes to add quantity rows.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if(count($customFields) > 0)
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5>{{ __('Custom Fields') }}</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        @foreach($customFields as $field)
                                            <div class="col-md-6 mb-3">
                                                <div class="form-group">
                                                    <label for="custom_field_{{ $field->id }}" class="form-label {{ $field->required ? 'mandatory' : '' }}">
                                                        {{ $field->name }}
                                                    </label>
                                                    
                                                    @if($field->type == 'textbox')
                                                        <input type="text" 
                                                            id="custom_field_{{ $field->id }}" 
                                                            name="custom_fields[{{ $field->id }}]" 
                                                            class="form-control" 
                                                            {{ $field->required ? 'required' : '' }}
                                                            @if($field->min_length) minlength="{{ $field->min_length }}" @endif
                                                            @if($field->max_length) maxlength="{{ $field->max_length }}" @endif>
                                                    @elseif($field->type == 'number')
                                                        <input type="number" 
                                                            id="custom_field_{{ $field->id }}" 
                                                            name="custom_fields[{{ $field->id }}]" 
                                                            class="form-control" 
                                                            {{ $field->required ? 'required' : '' }}>
                                                    @elseif($field->type == 'textarea')
                                                        <textarea 
                                                            id="custom_field_{{ $field->id }}" 
                                                            name="custom_fields[{{ $field->id }}]" 
                                                            class="form-control" 
                                                            rows="3"
                                                            {{ $field->required ? 'required' : '' }}
                                                            @if($field->min_length) minlength="{{ $field->min_length }}" @endif
                                                            @if($field->max_length) maxlength="{{ $field->max_length }}" @endif></textarea>
                                                    @elseif($field->type == 'dropdown')
                                                        <select 
                                                            id="custom_field_{{ $field->id }}" 
                                                            name="custom_fields[{{ $field->id }}]" 
                                                            class="form-control select2" 
                                                            {{ $field->required ? 'required' : '' }}>
                                                            <option value="">{{ __('Select') }}</option>
                                                            @foreach($field->values as $value)
                                                                <option value="{{ $value }}">{{ $value }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif($field->type == 'radio')
                                                        <select 
                                                            id="custom_field_{{ $field->id }}" 
                                                            name="custom_fields[{{ $field->id }}]" 
                                                            class="form-control select2" 
                                                            {{ $field->required ? 'required' : '' }}>
                                                            <option value="">{{ __('Select') }}</option>
                                                            @foreach($field->values as $value)
                                                                <option value="{{ $value }}" {{ old('custom_fields.' . $field->id) == $value ? 'selected' : '' }}>{{ $value }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif($field->type == 'checkbox')
                                                        @php
                                                            $selectedValues = old('custom_fields.' . $field->id, []);
                                                            if (! is_array($selectedValues)) {
                                                                $selectedValues = [$selectedValues];
                                                            }
                                                        @endphp
                                                        <select 
                                                            id="custom_field_{{ $field->id }}" 
                                                            name="custom_fields[{{ $field->id }}][]" 
                                                            class="form-control select2" 
                                                            multiple>
                                                            @foreach($field->values as $value)
                                                                <option value="{{ $value }}" {{ in_array($value, $selectedValues, true) ? 'selected' : '' }}>{{ $value }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif($field->type == 'fileinput')
                                                        <input 
                                                            type="file" 
                                                            id="custom_field_{{ $field->id }}" 
                                                            name="custom_field_files[{{ $field->id }}]" 
                                                            class="form-control"
                                                            {{ $field->required ? 'required' : '' }}>
                                                    @endif
                                                    
                                                    @if($field->notes)
                                                        <div class="form-text text-muted">{{ $field->notes }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="row mt-4">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary me-1 mb-1">{{ __('Submit') }}</button>
                            <a href="{{ route('item.shein.products') }}" class="btn btn-light-secondary me-1 mb-1">{{ __('Cancel') }}</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <template id="optionRowTemplate">
            <div class="row g-3 align-items-end" data-repeater-row>
                <div class="col-md-10">
                    <input type="text" name="custom_options[__INDEX__]" class="form-control" placeholder="{{ __('merchant_products.form.custom_placeholder') }}">
                </div>
                <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm" data-remove-row>
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </template>
    </section>
@endsection

@section('script')
    <script>
        $(document).ready(function() {
            $('.select2').each(function() {
                const $el = $(this);
                const isMulti = $el.prop('multiple');
                const isSizeSelector = $el.is('[data-size-select]');
                const options = { width: '100%' };

                if (isMulti) {
                    options.closeOnSelect = false;
                }
                if (isSizeSelector) {
                    options.tags = false;
                }

                $el.select2(options);
            });

            const setupRepeater = (containerSelector, templateId, addSelector) => {
                const container = document.querySelector(containerSelector);
                const template = document.getElementById(templateId);
                const addButton = document.querySelector(addSelector);

                if (!container || !template || !addButton) {
                    return;
                }

                let index = container.querySelectorAll('[data-repeater-row]').length;

                const addRow = () => {
                    const html = template.innerHTML.replace(/__INDEX__/g, index++);
                    const fragment = document.createElement('div');
                    fragment.innerHTML = html.trim();
                    const row = fragment.firstElementChild;
                    container.appendChild(row);
                };

                addButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    addRow();
                });

                container.addEventListener('click', (event) => {
                    if (event.target.closest('[data-remove-row]')) {
                        event.preventDefault();
                        const row = event.target.closest('[data-repeater-row]');
                        if (row) {
                            row.remove();
                        }
                    }
                });
            };

            setupRepeater('[data-option-rows]', 'optionRowTemplate', '[data-add-option]');

            // Image preview
            $('#image').change(function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#image_preview').attr('src', e.target.result).show();
                    }
                    reader.readAsDataURL(file);
                }
            });

            // Gallery images preview
            $('#gallery_images').change(function() {
                const files = this.files;
                $('#gallery_preview').empty();
                
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        $('#gallery_preview').append(`
                            <div class="me-2 mb-2">
                                <img src="${e.target.result}" alt="Gallery Image" style="max-width: 100px; max-height: 100px;">
                            </div>
                        `);
                    }
                    
                    reader.readAsDataURL(file);
                }
            });

            const sizeHeader = @json(__('merchant_products.form.sizes_title'));
            const stockHeader = @json(__('merchant_products.form.stock'));
            const variantStocksSeed = @json($variantStocks);
            const variantStockMap = {};
            const sizeCatalogValues = @json($sizeCatalogValues ?? []);
            const importRoute = @json(route('item.shein.products.import'));

            const colorSelect = document.querySelector('[data-color-select]');
            const sizeSelect = document.querySelector('[data-size-select]');
            const colorInputs = document.querySelector('[data-color-inputs]');
            const sizeInputs = document.querySelector('[data-size-inputs]');
            const variantGrid = document.querySelector('[data-variant-grid]');
            const variantEmpty = document.querySelector('[data-variant-empty]');
            const customColorButton = document.querySelector('[data-add-custom-color]');
            const customColorCode = document.querySelector('[data-custom-color-code]');
            const customColorLabel = document.querySelector('[data-custom-color-label]');
            const importButton = document.getElementById('shein_import_btn');
            let importStatus = document.getElementById('shein_import_status');
            const importPreview = document.getElementById('imported_images_preview');
            const importInputs = document.getElementById('imported_images_inputs');
            const productLinkInput = document.getElementById('product_link');
            const imageInput = document.getElementById('image');
            const importedState = { images: [] };
            const importButtonLabel = importButton ? importButton.textContent : 'Fetch Shein Data';
            const importedSeed = @json(old('imported_images', []));

            const normalizeColorCode = (value) => {
                if (value === null || value === undefined) {
                    return '';
                }
                let code = value.toString().trim().toUpperCase();
                if (code === '') {
                    return '';
                }
                if (!code.startsWith('#')) {
                    code = `#${code.replace(/^#/, '')}`;
                }
                return code;
            };

            const normalizeSizeValue = (value) => {
                if (value === null || value === undefined) {
                    return '';
                }
                return value.toString().trim();
            };

            const normalizeText = (value) => {
                if (value === null || value === undefined) {
                    return '';
                }
                return value.toString().trim();
            };

            const parsePrice = (value) => {
                const text = normalizeText(value).replace(/,/g, '');
                const match = text.match(/-?\d+(\.\d+)?/);
                return match ? match[0] : '';
            };

            const sizeTokens = new Set((sizeCatalogValues || []).map((value) => normalizeText(value).toUpperCase()));

            const looksLikeSize = (value) => {
                const text = normalizeText(value).toUpperCase();
                if (text === '') {
                    return false;
                }
                if (sizeTokens.has(text)) {
                    return true;
                }
                return /^[0-9]{1,3}(\.[0-9]+)?$/.test(text);
            };

            const escapeHtml = (value) => {
                const text = value === null || value === undefined ? '' : value.toString();
                return text
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };

            const escapeAttribute = (value) => escapeHtml(value);

            const appendHiddenInput = (container, name, value) => {
                if (!container) {
                    return;
                }
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                container.appendChild(input);
            };

            const updateImageRequirement = () => {
                if (imageInput) {
                    imageInput.required = importedState.images.length === 0;
                }
            };

            const setImportStatus = (message, isError = false) => {
                if (!importStatus && importButton && importButton.parentElement) {
                    importStatus = document.createElement('small');
                    importStatus.id = 'shein_import_status';
                    importStatus.className = 'form-text text-muted';
                    importButton.parentElement.appendChild(importStatus);
                }
                if (!importStatus) {
                    return;
                }
                importStatus.textContent = message || '';
                importStatus.classList.toggle('text-danger', isError);
                importStatus.classList.toggle('text-success', !isError && message);
            };

            const setImportBusy = (busy) => {
                if (!importButton) {
                    return;
                }
                importButton.disabled = busy;
                importButton.textContent = busy ? 'Fetching...' : importButtonLabel;
            };

            const rgbToHex = (value) => {
                const match = String(value).match(/rgb[a]?\\((\\d+),\\s*(\\d+),\\s*(\\d+)/i);
                if (!match) {
                    return '';
                }
                const r = Number(match[1]);
                const g = Number(match[2]);
                const b = Number(match[3]);
                if ([r, g, b].some((v) => Number.isNaN(v))) {
                    return '';
                }
                return `#${[r, g, b].map((v) => v.toString(16).padStart(2, '0')).join('')}`.toUpperCase();
            };

            const hashToColor = (label) => {
                const text = normalizeText(label);
                let hash = 7;
                for (let i = 0; i < text.length; i += 1) {
                    hash = (hash * 31 + text.charCodeAt(i)) >>> 0;
                }
                const hex = (hash & 0xffffff).toString(16).padStart(6, '0');
                return `#${hex.toUpperCase()}`;
            };

            const normalizeImportedColorCode = (value, label) => {
                const text = normalizeText(value);
                if (text !== '') {
                    if (text.startsWith('rgb')) {
                        const rgb = rgbToHex(text);
                        if (rgb) {
                            return normalizeColorCode(rgb);
                        }
                    }
                    if (/^#?[0-9a-f]{6}$/i.test(text)) {
                        return normalizeColorCode(text);
                    }
                }
                return normalizeColorCode(hashToColor(label || 'color'));
            };

            const renderImportedImages = () => {
                if (!importPreview || !importInputs) {
                    return;
                }
                importPreview.innerHTML = '';
                importInputs.innerHTML = '';

                importedState.images.forEach((url, index) => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'position-relative';
                    wrapper.style.width = '100px';
                    wrapper.style.height = '100px';
                    wrapper.innerHTML = `
                        <img src="${escapeAttribute(url)}" alt="Imported" style="width:100%;height:100%;object-fit:cover;border-radius:6px;border:1px solid #e5e5e5;">
                        <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" data-remove-imported="${index}" style="transform: translate(35%, -35%);padding:2px 6px;line-height:1;">
                            <i class="bi bi-x"></i>
                        </button>
                    `;
                    importPreview.appendChild(wrapper);
                    appendHiddenInput(importInputs, `imported_images[${index}]`, url);
                });

                updateImageRequirement();
            };

            if (Array.isArray(importedSeed) && importedSeed.length) {
                importedState.images = importedSeed
                    .map((url) => normalizeText(url))
                    .filter((url) => url !== '');
                renderImportedImages();
            }

            const getColorLabel = (code) => {
                if (!colorSelect) {
                    return '';
                }
                const options = Array.from(colorSelect.options);
                for (const option of options) {
                    if (normalizeColorCode(option.value) === code) {
                        const dataLabel = option.getAttribute('data-label');
                        if (dataLabel && dataLabel.trim() !== '') {
                            return dataLabel.trim();
                        }
                        const text = (option.textContent || '').trim();
                        if (text === '' || text === code) {
                            return '';
                        }
                        const suffix = `(${code})`;
                        if (text.endsWith(suffix)) {
                            return text.slice(0, -suffix.length).trim();
                        }
                        return text;
                    }
                }
                return '';
            };

            const ensureSelectOption = (select, value, text, dataLabel = null) => {
                if (!select) {
                    return;
                }
                const normalized = normalizeText(value);
                if (normalized === '') {
                    return;
                }
                const existing = Array.from(select.options).find((option) => normalizeText(option.value) === normalized);
                const label = text || normalized;

                if (existing) {
                    existing.textContent = label;
                    if (dataLabel !== null) {
                        existing.setAttribute('data-label', dataLabel);
                    }
                    return;
                }

                const option = new Option(label, normalized, false, false);
                if (dataLabel !== null) {
                    option.setAttribute('data-label', dataLabel);
                }
                select.appendChild(option);
            };

            const setSelectValues = (select, values) => {
                if (!select) {
                    return;
                }
                const unique = Array.from(new Set(values.map((value) => normalizeText(value)).filter((value) => value !== '')));
                $(select).val(unique).trigger('change');
            };

            if (Array.isArray(variantStocksSeed)) {
                variantStocksSeed.forEach((row) => {
                    const color = normalizeColorCode(row.color || '');
                    const size = normalizeSizeValue(row.size || '');
                    const stock = row.stock ?? '';
                    const key = `${color}||${size}`;
                    variantStockMap[key] = stock;
                });
            }

            const captureCurrentStockMap = () => {
                if (!variantGrid) {
                    return;
                }
                variantGrid.querySelectorAll('[data-variant-row]').forEach((row) => {
                    const color = row.getAttribute('data-color') || '';
                    const size = row.getAttribute('data-size') || '';
                    const stockInput = row.querySelector('input[name*="[stock]"]');
                    if (!stockInput) {
                        return;
                    }
                    const key = `${color}||${size}`;
                    variantStockMap[key] = stockInput.value;
                });
            };

            const collectSelectedColors = () => {
                if (!colorSelect) {
                    return [];
                }
                const values = $(colorSelect).val() || [];
                const seen = new Set();
                const result = [];
                values.forEach((value) => {
                    const code = normalizeColorCode(value);
                    if (code === '' || seen.has(code)) {
                        return;
                    }
                    seen.add(code);
                    result.push({
                        code,
                        label: getColorLabel(code),
                    });
                });
                return result;
            };

            const collectSelectedSizes = () => {
                if (!sizeSelect) {
                    return [];
                }
                const values = $(sizeSelect).val() || [];
                const seen = new Set();
                const result = [];
                values.forEach((value) => {
                    const size = normalizeSizeValue(value);
                    if (size === '' || seen.has(size)) {
                        return;
                    }
                    seen.add(size);
                    result.push(size);
                });
                return result;
            };

            const syncColorInputs = (colors) => {
                if (!colorInputs) {
                    return;
                }
                colorInputs.innerHTML = '';
                colors.forEach((color, index) => {
                    appendHiddenInput(colorInputs, `colors[${index}][code]`, color.code);
                    if (color.label) {
                        appendHiddenInput(colorInputs, `colors[${index}][label]`, color.label);
                    }
                });
            };

            const syncSizeInputs = (sizes) => {
                if (!sizeInputs) {
                    return;
                }
                sizeInputs.innerHTML = '';
                sizes.forEach((size, index) => {
                    appendHiddenInput(sizeInputs, `sizes[${index}][value]`, size);
                });
            };

            const buildVariantTables = (colors, sizes) => {
                if (!variantGrid) {
                    return;
                }
                captureCurrentStockMap();

                variantGrid.innerHTML = '';

                if (colors.length === 0 || sizes.length === 0) {
                    if (variantEmpty) {
                        variantEmpty.classList.remove('d-none');
                    }
                    return;
                }

                if (variantEmpty) {
                    variantEmpty.classList.add('d-none');
                }

                let index = 0;

                colors.forEach((color) => {
                    const colorCode = normalizeColorCode(color.code || '');
                    const colorLabel = color.label || colorCode || '-';
                    let rowsHtml = '';

                    sizes.forEach((size) => {
                        const sizeValue = normalizeSizeValue(size || '');
                        const key = `${colorCode}||${sizeValue}`;
                        const stockValue = variantStockMap[key] ?? '';
                        const sizeLabel = sizeValue || '-';

                        rowsHtml += `
                            <tr data-variant-row data-color="${escapeAttribute(colorCode)}" data-size="${escapeAttribute(sizeValue)}">
                                <td>${escapeHtml(sizeLabel)}</td>
                                <td>
                                    <input type="hidden" name="variant_stocks[${index}][color]" value="${escapeAttribute(colorCode)}">
                                    <input type="hidden" name="variant_stocks[${index}][size]" value="${escapeAttribute(sizeValue)}">
                                    <input type="number" name="variant_stocks[${index}][stock]" class="form-control" min="0" value="${escapeAttribute(stockValue)}">
                                </td>
                            </tr>
                        `;
                        index += 1;
                    });

                    const swatch = colorCode !== '' ? `
                        <span class="me-2" style="width:16px;height:16px;border-radius:3px;border:1px solid #ccc;display:inline-block;background:${escapeAttribute(colorCode)};"></span>
                    ` : '';

                    variantGrid.insertAdjacentHTML('beforeend', `
                        <div class="mb-4" data-color-block data-color-code="${escapeAttribute(colorCode)}">
                            <div class="d-flex align-items-center mb-2">
                                ${swatch}
                                <strong>${escapeHtml(colorLabel)}</strong>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle">
                                    <thead>
                                        <tr>
                                            <th>${escapeHtml(sizeHeader)}</th>
                                            <th>${escapeHtml(stockHeader)}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${rowsHtml}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `);
                });
            };

            const refreshVariants = () => {
                const colors = collectSelectedColors();
                const sizes = collectSelectedSizes();
                syncColorInputs(colors);
                syncSizeInputs(sizes);
                buildVariantTables(colors, sizes);
            };

            if (colorSelect) {
                $(colorSelect).on('change', refreshVariants);
            }

            if (sizeSelect) {
                $(sizeSelect).on('change', refreshVariants);
            }

            if (customColorButton && colorSelect) {
                customColorButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    const rawCode = customColorCode ? customColorCode.value : '';
                    const code = normalizeColorCode(rawCode);
                    if (code === '') {
                        return;
                    }
                    const label = customColorLabel ? customColorLabel.value.toString().trim() : '';
                    const displayText = label !== '' ? `${label} (${code})` : code;
                    let option = null;
                    if (colorSelect) {
                        option = Array.from(colorSelect.options).find((entry) => normalizeColorCode(entry.value) === code) || null;
                    }
                    if (!option) {
                        option = new Option(displayText, code, true, true);
                        option.setAttribute('data-label', label);
                        colorSelect.appendChild(option);
                    } else {
                        option.textContent = displayText;
                        option.setAttribute('data-label', label);
                        option.selected = true;
                    }

                    const current = $(colorSelect).val() || [];
                    if (!current.includes(code)) {
                        current.push(code);
                    }
                    $(colorSelect).val(current).trigger('change');

                    if (customColorLabel) {
                        customColorLabel.value = '';
                    }
                });
            }

            if (importPreview) {
                importPreview.addEventListener('click', (event) => {
                    const target = event.target.closest('[data-remove-imported]');
                    if (!target) {
                        return;
                    }
                    const index = Number(target.getAttribute('data-remove-imported'));
                    if (Number.isNaN(index)) {
                        return;
                    }
                    importedState.images.splice(index, 1);
                    renderImportedImages();
                });
            }

            if (imageInput) {
                imageInput.addEventListener('change', updateImageRequirement);
            }

            const findPropertyByName = (properties, needle) => {
                const lowered = needle.toLowerCase();
                return properties.find((property) => normalizeText(property.name).toLowerCase().includes(lowered));
            };

            const findSizeProperty = (properties) => {
                if (!Array.isArray(properties)) {
                    return null;
                }
                const named = findPropertyByName(properties, 'size');
                if (named) {
                    return named;
                }
                let best = null;
                let bestScore = 0;
                properties.forEach((property) => {
                    const items = Array.isArray(property.items) ? property.items : [];
                    const score = items.filter((item) => looksLikeSize(item.name)).length;
                    if (score > bestScore) {
                        bestScore = score;
                        best = property;
                    }
                });
                return best;
            };

            const findColorProperty = (properties, sizeProperty) => {
                if (!Array.isArray(properties)) {
                    return null;
                }
                const named = findPropertyByName(properties, 'color') || findPropertyByName(properties, 'colour');
                if (named && named !== sizeProperty) {
                    return named;
                }
                let best = null;
                let bestScore = 0;
                properties.forEach((property) => {
                    if (property === sizeProperty) {
                        return;
                    }
                    const items = Array.isArray(property.items) ? property.items : [];
                    const score = items.filter((item) => {
                        if (normalizeText(item.color) !== '' || normalizeText(item.image) !== '') {
                            return true;
                        }
                        return /^#?[0-9a-f]{6}$/i.test(normalizeText(item.name));
                    }).length;
                    if (score > bestScore) {
                        bestScore = score;
                        best = property;
                    }
                });
                if (best) {
                    return best;
                }
                let fallback = null;
                let fallbackCount = 0;
                properties.forEach((property) => {
                    if (property === sizeProperty) {
                        return;
                    }
                    const items = Array.isArray(property.items) ? property.items : [];
                    if (items.length > fallbackCount) {
                        fallbackCount = items.length;
                        fallback = property;
                    }
                });
                return fallback;
            };

            const applyVariantStocks = (variants, colors, sizes) => {
                if (!Array.isArray(variants)) {
                    return;
                }
                const sizeLookup = new Map(
                    sizes.map((size) => [normalizeText(size).toLowerCase(), normalizeText(size)])
                );
                const colorLookup = new Map();
                colors.forEach((color) => {
                    const code = normalizeColorCode(color.code);
                    if (color.label) {
                        colorLookup.set(normalizeText(color.label).toLowerCase(), code);
                    }
                    colorLookup.set(code.toLowerCase(), code);
                });

                Object.keys(variantStockMap).forEach((key) => {
                    delete variantStockMap[key];
                });

                variants.forEach((variant) => {
                    if (!variant || typeof variant !== 'object') {
                        return;
                    }
                    const stock = variant.stock;
                    if (stock === null || stock === undefined || stock === '') {
                        return;
                    }
                    const values = Object.values(variant.props || {}).map(normalizeText).filter((value) => value !== '');
                    let sizeValue = null;
                    let colorCode = null;

                    values.forEach((value) => {
                        const key = value.toLowerCase();
                        if (!sizeValue && sizeLookup.has(key)) {
                            sizeValue = sizeLookup.get(key);
                        }
                        if (!colorCode && colorLookup.has(key)) {
                            colorCode = colorLookup.get(key);
                        }
                    });

                    if (sizeValue && colorCode) {
                        const key = `${normalizeColorCode(colorCode)}||${normalizeSizeValue(sizeValue)}`;
                        variantStockMap[key] = stock;
                    }
                });
            };

            const applyImportData = (data) => {
                if (!data || typeof data !== 'object') {
                    setImportStatus('No data returned from Shein.', true);
                    return;
                }

                const title = normalizeText(data.title);
                if (title !== '') {
                    $('#name').val(title);
                }

                const description = normalizeText(data.description);
                if (description !== '') {
                    $('#description').val(description);
                }

                const price = parsePrice(data.price);
                if (price !== '') {
                    $('#price').val(price);
                }

                const currency = normalizeText(data.currency).toUpperCase();
                if (currency !== '' && $('#currency option[value="' + currency + '"]').length) {
                    $('#currency').val(currency);
                }

                if (data.stockTotal !== null && data.stockTotal !== undefined && data.stockTotal !== '') {
                    $('#stock').val(data.stockTotal);
                }

                const images = Array.isArray(data.images) ? data.images : [];
                importedState.images = Array.from(new Set(images.map((url) => normalizeText(url)).filter((url) => url !== '')));
                renderImportedImages();

                const properties = Array.isArray(data.properties) ? data.properties : [];
                const variants = Array.isArray(data.variants) ? data.variants : [];

                const sizeProperty = findSizeProperty(properties);
                const colorProperty = findColorProperty(properties, sizeProperty);

                let sizes = [];
                if (sizeProperty && Array.isArray(sizeProperty.items)) {
                    sizes = sizeProperty.items
                        .map((item) => normalizeText(item.name))
                        .filter((value) => value !== '');
                }

                let colors = [];
                if (colorProperty && Array.isArray(colorProperty.items)) {
                    colors = colorProperty.items
                        .map((item) => {
                            const label = normalizeText(item.name);
                            if (label === '') {
                                return null;
                            }
                            const code = normalizeImportedColorCode(item.color, label);
                            return { code, label };
                        })
                        .filter((value) => value);
                }

                if (sizes.length === 0 && variants.length) {
                    const sizeSet = new Set();
                    variants.forEach((variant) => {
                        Object.values(variant.props || {}).forEach((value) => {
                            if (looksLikeSize(value)) {
                                sizeSet.add(normalizeText(value));
                            }
                        });
                    });
                    sizes = Array.from(sizeSet);
                }

                if (colors.length === 0 && variants.length) {
                    const colorSet = new Set();
                    variants.forEach((variant) => {
                        Object.values(variant.props || {}).forEach((value) => {
                            const text = normalizeText(value);
                            if (text !== '' && !looksLikeSize(text)) {
                                colorSet.add(text);
                            }
                        });
                    });
                    colors = Array.from(colorSet).map((label) => ({
                        label,
                        code: normalizeImportedColorCode(null, label),
                    }));
                }

                const uniqueSizes = Array.from(new Set(sizes.map((value) => normalizeText(value)).filter((value) => value !== '')));
                const uniqueColors = [];
                const colorSeen = new Set();
                colors.forEach((color) => {
                    if (!color || !color.code) {
                        return;
                    }
                    const code = normalizeColorCode(color.code);
                    if (colorSeen.has(code)) {
                        return;
                    }
                    colorSeen.add(code);
                    uniqueColors.push({
                        code,
                        label: normalizeText(color.label),
                    });
                });

                if (colorSelect) {
                    uniqueColors.forEach((color) => {
                        const displayText = color.label ? `${color.label} (${color.code})` : color.code;
                        ensureSelectOption(colorSelect, color.code, displayText, color.label || '');
                    });
                    setSelectValues(colorSelect, uniqueColors.map((color) => color.code));
                }

                if (sizeSelect) {
                    uniqueSizes.forEach((size) => {
                        ensureSelectOption(sizeSelect, size, size);
                    });
                    setSelectValues(sizeSelect, uniqueSizes);
                }

                applyVariantStocks(variants, uniqueColors, uniqueSizes);
                refreshVariants();

                setImportStatus('Shein data loaded.', false);
            };

            window.__sheinApplyImport = applyImportData;

            const triggerSheinImport = () => {
                if (!productLinkInput || !importButton || importButton.disabled) {
                    return;
                }

                const url = normalizeText(productLinkInput.value);
                if (url === '') {
                    setImportStatus('Paste the Shein product link first.', true);
                    return;
                }
                const csrfToken = document.querySelector('meta[name="csrf-token"]');
                const csrfValue = csrfToken ? csrfToken.getAttribute('content') : null;

                setImportBusy(true);
                setImportStatus('Fetching data from Shein...', false);

                $.ajax({
                    url: importRoute,
                    method: 'POST',
                    data: { url },
                    headers: csrfValue ? { 'X-CSRF-TOKEN': csrfValue } : {},
                })
                    .done((response) => {
                        if (response && response.error) {
                            const message = response.message || 'Failed to fetch Shein data.';
                            setImportStatus(message, true);
                            return;
                        }
                        const payload = response && response.data ? response.data : response;
                        applyImportData(payload);
                    })
                    .fail((xhr) => {
                        const message =
                            (xhr.responseJSON && xhr.responseJSON.message) ||
                            'Failed to fetch Shein data.';
                        setImportStatus(message, true);
                    })
                    .always(() => {
                        setImportBusy(false);
                    });
            };

            if (importButton) {
                if (importButton.dataset.sheinImportBound !== '1') {
                    importButton.dataset.sheinImportBound = '1';
                    $(document).on('click', '#shein_import_btn', (event) => {
                        event.preventDefault();
                        try {
                            triggerSheinImport();
                        } catch (error) {
                            setImportStatus(`Import failed: ${error}`, true);
                        }
                    });
                }
                setImportStatus('Ready to import.', false);
            }

            refreshVariants();
        });

        function formSuccessFunction(response) {
            if (!response.error) {
                window.location.href = response.redirect;
            }
        }
    </script>
@endsection 
