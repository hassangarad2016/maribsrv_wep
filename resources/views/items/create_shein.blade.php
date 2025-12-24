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
                    if (! is_array($colorRows) || $colorRows === []) {
                        $colorRows = [['code' => '#ffffff', 'label' => '', 'quantity' => null]];
                    } else {
                        $colorRows = array_values($colorRows);
                    }
                    $sizeRows = old('sizes');
                    if (! is_array($sizeRows) || $sizeRows === []) {
                        $sizeRows = [['value' => '']];
                    } else {
                        $sizeRows = array_values($sizeRows);
                    }
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
                                    <option value="EUR">EUR</option>
                                    <option value="GBP">GBP</option>
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
                                <input type="url" id="product_link" name="product_link" class="form-control @error('product_link') is-invalid @enderror" value="{{ old('product_link') }}" required maxlength="2048">
                                @error('product_link')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
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

                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5>{{ __('merchant_products.form.attributes_title') }}</h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-3">{{ __('merchant_products.form.attributes_description') }}</p>

                                    <div class="border rounded p-3 mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="mb-0">{{ __('merchant_products.form.colors_title') }}</h6>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-add-color>
                                                <i class="bi bi-plus-lg"></i>
                                                {{ __('merchant_products.form.add_color') }}
                                            </button>
                                        </div>
                                        <div class="row text-muted mb-2">
                                            <div class="col-md-3">{{ __('merchant_products.form.color_picker') }}</div>
                                            <div class="col-md-4">{{ __('merchant_products.form.color_label') }}</div>
                                            <div class="col-md-3">{{ __('merchant_products.form.color_quantity') }}</div>
                                        </div>
                                        <div class="d-flex flex-column gap-3" data-color-rows>
                                            @foreach ($colorRows as $index => $color)
                                                <div class="row g-3 align-items-end" data-repeater-row>
                                                    <div class="col-md-3">
                                                        <input type="color"
                                                               name="colors[{{ $index }}][code]"
                                                               class="form-control form-control-color"
                                                               value="{{ old('colors.' . $index . '.code', $color['code'] ?? '#ffffff') }}">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <input type="text" name="colors[{{ $index }}][label]"
                                                               class="form-control"
                                                               placeholder="{{ __('merchant_products.form.color_label_placeholder') }}"
                                                               value="{{ old('colors.' . $index . '.label', $color['label'] ?? '') }}">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <input type="number" min="0" name="colors[{{ $index }}][quantity]"
                                                               class="form-control"
                                                               placeholder="0"
                                                               value="{{ old('colors.' . $index . '.quantity', $color['quantity'] ?? '') }}">
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

                                    <div class="border rounded p-3 mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="mb-0">{{ __('merchant_products.form.sizes_title') }}</h6>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-add-size>
                                                <i class="bi bi-plus-lg"></i>
                                                {{ __('merchant_products.form.add_size') }}
                                            </button>
                                        </div>
                                        <div class="d-flex flex-column gap-3" data-size-rows>
                                            @foreach ($sizeRows as $index => $size)
                                                <div class="row g-3 align-items-end" data-repeater-row>
                                                    <div class="col-md-10">
                                                        @php
                                                            $currentSizeValue = old('sizes.' . $index . '.value', $size['value'] ?? '');
                                                            $normalizedSizeCatalog = collect($sizeCatalogList)->map(fn ($entry) => (string) $entry)->all();
                                                            $isCustomSize = $currentSizeValue !== '' && ! in_array($currentSizeValue, $normalizedSizeCatalog, true);
                                                        @endphp
                                                        <select name="sizes[{{ $index }}][value]" class="form-select">
                                                            <option value="">{{ __('merchant_products.form.size_placeholder') }}</option>
                                                            @foreach ($sizeCatalogList as $option)
                                                                @php
                                                                    $optionValue = (string) $option;
                                                                @endphp
                                                                <option value="{{ $optionValue }}" @selected($currentSizeValue === $optionValue)>{{ $optionValue }}</option>
                                                            @endforeach
                                                            @if ($isCustomSize)
                                                                <option value="{{ $currentSizeValue }}" selected>{{ $currentSizeValue }}</option>
                                                            @endif
                                                        </select>
                                                    </div>
                                                    <div class="col-md-2 text-end">
                                                        <button type="button" class="btn btn-outline-danger btn-sm" data-remove-row>
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        <small class="text-muted">{{ __('merchant_products.form.size_catalog_hint') }}</small>
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
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">{{ __('merchant_products.inventory.title') }}</h5>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-build-variants>
                                        <i class="bi bi-arrow-repeat"></i>
                                        Build variants
                                    </button>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-3">
                                        Variant stock rows are generated from the selected colors and sizes. If no variants are defined, the general stock field is used.
                                    </p>
                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle" data-variant-table>
                                            <thead>
                                                <tr>
                                                    <th>{{ __('merchant_products.form.colors_title') }}</th>
                                                    <th>{{ __('merchant_products.form.sizes_title') }}</th>
                                                    <th>{{ __('merchant_products.form.stock') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody data-variant-rows></tbody>
                                        </table>
                                    </div>
                                    <div class="text-muted small" data-variant-empty>
                                        No variants yet. Add colors or sizes, then click "Build variants".
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

        <template id="colorRowTemplate">
            <div class="row g-3 align-items-end" data-repeater-row>
                <div class="col-md-3">
                    <input type="color" name="colors[__INDEX__][code]" class="form-control form-control-color" value="#ffffff">
                </div>
                <div class="col-md-4">
                    <input type="text" name="colors[__INDEX__][label]" class="form-control" placeholder="{{ __('merchant_products.form.color_label_placeholder') }}">
                </div>
                <div class="col-md-3">
                    <input type="number" name="colors[__INDEX__][quantity]" class="form-control" min="0" placeholder="0">
                </div>
                <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm" data-remove-row>
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </template>

        <template id="sizeRowTemplate">
            <div class="row g-3 align-items-end" data-repeater-row>
                <div class="col-md-10">
                    <select name="sizes[__INDEX__][value]" class="form-select">
                        <option value="">{{ __('merchant_products.form.size_placeholder') }}</option>
                        @foreach ($sizeCatalogList as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm" data-remove-row>
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </template>

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
            // Initialize Select2
            $('.select2').select2({
                width: '100%'
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

            setupRepeater('[data-color-rows]', 'colorRowTemplate', '[data-add-color]');
            setupRepeater('[data-size-rows]', 'sizeRowTemplate', '[data-add-size]');
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

            const variantStocksSeed = @json($variantStocks);
            const variantStockMap = {};

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

            const escapeHtml = (value) => {
                const text = value === null || value === undefined ? '' : value.toString();
                return text
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\"/g, '&quot;')
                    .replace(/'/g, '&#039;');
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

            const variantRowsContainer = document.querySelector('[data-variant-rows]');
            const variantEmpty = document.querySelector('[data-variant-empty]');

            const captureCurrentStockMap = () => {
                if (!variantRowsContainer) {
                    return;
                }
                variantRowsContainer.querySelectorAll('[data-variant-row]').forEach((row) => {
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

            const collectColors = () => {
                const rows = document.querySelectorAll('[data-color-rows] [data-repeater-row]');
                const result = [];
                const seen = new Set();
                rows.forEach((row) => {
                    const codeInput = row.querySelector('input[name*="[code]"]');
                    if (!codeInput) {
                        return;
                    }
                    const code = normalizeColorCode(codeInput.value);
                    if (code === '' || seen.has(code)) {
                        return;
                    }
                    const labelInput = row.querySelector('input[name*="[label]"]');
                    const label = labelInput ? labelInput.value.toString().trim() : '';
                    seen.add(code);
                    result.push({ code, label });
                });
                return result;
            };

            const collectSizes = () => {
                const rows = document.querySelectorAll('[data-size-rows] [data-repeater-row]');
                const result = [];
                const seen = new Set();
                rows.forEach((row) => {
                    const select = row.querySelector('select[name*="[value]"]');
                    const input = row.querySelector('input[name*="[value]"]');
                    const raw = select ? select.value : (input ? input.value : '');
                    const value = normalizeSizeValue(raw);
                    if (value === '' || seen.has(value)) {
                        return;
                    }
                    seen.add(value);
                    result.push(value);
                });
                return result;
            };

            const buildVariantRows = () => {
                if (!variantRowsContainer) {
                    return;
                }
                captureCurrentStockMap();

                const colors = collectColors();
                const sizes = collectSizes();
                const hasVariants = colors.length > 0 || sizes.length > 0;

                variantRowsContainer.innerHTML = '';

                if (!hasVariants) {
                    if (variantEmpty) {
                        variantEmpty.classList.remove('d-none');
                    }
                    return;
                }

                if (variantEmpty) {
                    variantEmpty.classList.add('d-none');
                }

                const colorList = colors.length ? colors : [{ code: '', label: '' }];
                const sizeList = sizes.length ? sizes : [''];
                let index = 0;

                colorList.forEach((color) => {
                    sizeList.forEach((size) => {
                        const colorCode = normalizeColorCode(color.code || '');
                        const sizeValue = normalizeSizeValue(size || '');
                        const key = `${colorCode}||${sizeValue}`;
                        const stockValue = variantStockMap[key] ?? '';
                        const colorLabel = color.label || colorCode || '-';
                        const sizeLabel = sizeValue || '-';

                        variantRowsContainer.insertAdjacentHTML('beforeend', `
                            <tr data-variant-row data-color="${colorCode}" data-size="${sizeValue}">
                                <td>${escapeHtml(colorLabel)}</td>
                                <td>${escapeHtml(sizeLabel)}</td>
                                <td>
                                    <input type="hidden" name="variant_stocks[${index}][color]" value="${colorCode}">
                                    <input type="hidden" name="variant_stocks[${index}][size]" value="${sizeValue}">
                                    <input type="number" name="variant_stocks[${index}][stock]" class="form-control" min="0" value="${stockValue}">
                                </td>
                            </tr>
                        `);
                        index += 1;
                    });
                });
            };

            const buildButton = document.querySelector('[data-build-variants]');
            if (buildButton) {
                buildButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    buildVariantRows();
                });
            }

            buildVariantRows();
        });

        function formSuccessFunction(response) {
            if (!response.error) {
                window.location.href = response.redirect;
            }
        }
    </script>
@endsection 
