{{-- resources/views/services/create.blade.php --}}
@extends('layouts.main')

@section('title') {{ __('services.titles.create') }} @endsection

@section('page-title')
<div class="page-title">
  <div class="row">
    <div class="col-12 col-md-6 order-md-1 order-last"><h4>@yield('title')</h4></div>
    <div class="col-12 col-md-6 order-md-2 order-first">
      <nav aria-label="breadcrumb" class="breadcrumb-header float-end float-lg-end">
        <ol class="breadcrumb">
          <li class="breadcrumb-item">
            <a href="{{ route('services.index') }}" class="btn btn-outline-primary">
              <i class="bi bi-arrow-left"></i> {{ __('services.buttons.back_to_services') }}
            </a>
          </li>
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
      <form action="{{ route('services.store') }}" method="POST" enctype="multipart/form-data" id="serviceForm" class="create-form" data-parsley-validate>
        @csrf
        
        <div class="row">

          {{-- Category --}}
          <div class="col-md-6 mb-3">
            <label for="category_id" class="form-label">{{ __('services.labels.category') }} <span class="text-danger">*</span></label>
            <div class="form-control-plaintext fw-semibold">{{ $category->name }}</div>
            <input type="hidden" name="category_id" id="category_id" value="{{ old('category_id', $category->id) }}">
            @error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
          </div>


          {{-- Owner --}}
          <div class="col-md-6 mb-3">
            <label for="owner_id" class="form-label">{{ __('services.labels.service_owner') }}</label>
            <select name="owner_id" id="owner_id" class="form-select @error('owner_id') is-invalid @enderror">
              <option value="">{{ __('services.labels.select_owner') }}</option>
              @foreach(($owners ?? []) as $owner)
                <option value="{{ $owner->id }}" {{ old('owner_id')==$owner->id?'selected':'' }}>{{ $owner->name }} (ID: {{ $owner->id }})</option>
              @endforeach
            </select>
            <small class="text-muted">{{ __('services.messages.optional_assign_owner') }}</small>
            @error('owner_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>


          {{-- Title --}}
          <div class="col-md-6 mb-3">
            <label for="title" class="form-label">{{ __('services.labels.title') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" required>
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          {{-- Description --}}
          <div class="col-md-12 mb-3">
            <label for="tinymce_editor" class="form-label">{{ __('services.labels.description') }}</label>
            <textarea id="tinymce_editor" class="form-control @error('description') is-invalid @enderror" name="description" rows="5">{{ old('description') }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          {{-- Image --}}
          <div class="col-md-6 mb-3">
            <label for="image" class="form-label">{{ __('services.labels.image') }} <span class="text-danger">*</span></label>
            <input type="file" class="form-control @error('image') is-invalid @enderror" id="image" name="image" required>
            <small class="text-muted">{{ __('services.messages.recommended_image') }}</small>
            @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          {{-- Icon --}}
          <div class="col-md-6 mb-3">
            <label for="icon" class="form-label">{{ __('services.labels.icon') }}</label>
            <input type="file" class="form-control @error('icon') is-invalid @enderror" id="icon" name="icon">
            <small class="text-muted">{{ __('services.messages.recommended_icon') }}</small>
            @error('icon')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          {{-- Status --}}
          <div class="col-md-6 mb-3">
            <label class="form-label d-block">{{ __('services.labels.status') }}</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="status" id="status_active" value="1" {{ old('status','1')=='1'?'checked':'' }}>
              <label class="form-check-label" for="status_active">{{ __('services.labels.active') }}</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="status" id="status_inactive" value="0" {{ old('status')=='0'?'checked':'' }}>
              <label class="form-check-label" for="status_inactive">{{ __('services.labels.inactive') }}</label>
            </div>
            @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
          </div>

          {{-- Is Main --}}
          <div class="col-md-6 mb-3">
            <label class="form-label d-block">{{ __('services.labels.is_main_service') }}</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="is_main" id="is_main_yes" value="1" {{ old('is_main')=='1'?'checked':'' }}>
              <label class="form-check-label" for="is_main_yes">{{ __('services.labels.yes') }}</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="is_main" id="is_main_no" value="0" {{ old('is_main','0')=='0'?'checked':'' }}>
              <label class="form-check-label" for="is_main_no">{{ __('services.labels.no') }}</label>
            </div>
            @error('is_main')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
          </div>

          {{-- Payment --}}
          <div class="col-12 mb-2"><hr class="my-2"><h6 class="mb-3">{{ __('services.labels.payment_options') }}</h6></div>

          <div class="col-md-4 mb-3">
            <input type="hidden" name="is_paid" value="0">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="is_paid" name="is_paid" value="1" {{ old('is_paid')?'checked':'' }}>
              <label class="form-check-label" for="is_paid">{{ __('services.labels.is_paid_service') }}</label>
            </div>
            @error('is_paid')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4 mb-3 paid-fields">
            <label for="price" class="form-label">{{ __('services.labels.price') }}</label>
            <input type="number" step="0.01" min="0" class="form-control @error('price') is-invalid @enderror" id="price" name="price" value="{{ old('price') }}">
            @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4 mb-3 paid-fields">
            <label for="currency" class="form-label">{{ __('services.labels.currency') }}</label>
            <select id="currency" name="currency" class="form-select @error('currency') is-invalid @enderror">
              <option value="">{{ __('services.labels.select_currency') }}</option>
              <option value="YER" {{ old('currency')==='YER'?'selected':'' }}>{{ __('services.currencies.yer') }}</option>
              <option value="USD" {{ old('currency')==='USD'?'selected':'' }}>{{ __('services.currencies.usd') }}</option>
              <option value="SAR" {{ old('currency')==='SAR'?'selected':'' }}>{{ __('services.currencies.sar') }}</option>
            </select>
            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-12 mb-3 paid-fields">
            <label for="price_note" class="form-label">{{ __('services.labels.price_note_optional') }}</label>
            <textarea id="price_note" name="price_note" rows="2" class="form-control @error('price_note') is-invalid @enderror">{{ old('price_note') }}</textarea>
            @error('price_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          {{-- Custom Fields --}}
          <div class="col-12 mb-2"><hr class="my-2"><h6 class="mb-3">{{ __('services.labels.custom_fields') }}</h6></div>

          <div class="col-md-6 mb-3">
            <input type="hidden" name="has_custom_fields" value="0">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="has_custom_fields" name="has_custom_fields" value="1" {{ old('has_custom_fields')?'checked':'' }}>
              <label class="form-check-label" for="has_custom_fields">{{ __('services.labels.use_service_custom_fields') }}</label>
            </div>
            <small class="text-muted d-block mt-1">{{ __('services.messages.define_custom_fields') }}</small>
            @error('has_custom_fields')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
          </div>

          {{-- Builder (مطابق لواجهة الحقول في الإعلانات، مع 7 أنواع فقط) --}}
          <div id="cf_builder_wrap" class="col-12 mb-3" style="display:none;">
            <input type="hidden" name="service_fields_schema" id="service_fields_schema">

            {{-- نموذج إضافة حقل --}}
            <div class="border rounded p-3 mb-3">
              <div class="row g-3">
                <div class="col-md-12">
                  <label class="mandatory form-label">{{ __('services.labels.field_name') }} *</label>
                  <input type="text" id="cf_name" class="form-control" placeholder="">
                </div>

                <div class="col-md-12">
                  <label class="mandatory form-label">{{ __('services.labels.field_type') }} *</label>
                  <select id="cf_type" class="form-select">
                    <option value="number">{{ __('services.field_types.number_input') }}</option>
                    <option value="textbox">{{ __('services.field_types.text_input') }}</option>
                    <option value="fileinput">{{ __('services.field_types.file_input') }}</option>
                    <option value="radio">{{ __('services.field_types.radio') }}</option>
                    <option value="dropdown">{{ __('services.field_types.dropdown') }}</option>
                    <option value="checkbox">{{ __('services.field_types.checkboxes') }}</option>
                    <option value="color">{{ __('services.field_types.color_picker') }}</option>
                  </select>
                </div>

                {{-- طول الحقل (يظهر للأنواع النص/الرقم فقط) --}}
                <div class="col-md-6 min-max-fields">
                  <label class="form-label">{{ __('services.labels.field_length_min') }}</label>
                  <input type="number" id="cf_min" class="form-control" min="1">
                  <div class="input_hint">
                    {{ __('services.messages.apply_only_for') }}:
                    <text class="highlighted_text">{{ __('services.field_types.text') }}, {{ __('services.field_types.number') }}, {{ __('services.field_types.textarea') }}</text>.
                  </div>
                </div>
                <div class="col-md-6 min-max-fields">
                  <label class="form-label">{{ __('services.labels.field_length_max') }}</label>
                  <input type="number" id="cf_max" class="form-control" min="1">
                  <div class="input_hint">
                    {{ __('services.messages.apply_only_for') }}:
                    <text class="highlighted_text">{{ __('services.field_types.text') }}, {{ __('services.field_types.number') }}, {{ __('services.field_types.textarea') }}</text>.
                  </div>
                </div>

                {{-- قيّم للأنواع radio/dropdown/checkbox --}}
                <div class="col-md-12" id="field-values-div" style="display:none;">
                  <label class="form-label">{{ __('services.labels.field_values') }}</label>
                  <select id="cf_values" data-tags="true" data-token-separators="[',']"
                          data-placeholder="{{ __('services.labels.select_option') }}" data-allow-clear="true"
                          class="select2 w-100 full-width-select2" multiple="multiple"></select>
                  <div class="input_hint">
                    {{ __('services.messages.apply_only_for') }}:
                    <text class="highlighted_text">{{ __('services.field_types.checkboxes') }}, {{ __('services.field_types.radio') }}</text>
                    {{ __('services.field_types.and') }}
                    <text class="highlighted_text">{{ __('services.field_types.dropdown') }}</text>.
                  </div>
                </div>

                {{-- مُنتقي الألوان للنوع color --}}
                <div class="col-md-12" id="color-picker-div" style="display:none;">
                  <label class="form-label">{{ __('services.labels.color_values') }}</label>
                  <div id="color-container">
                    <div class="color-input-group mb-2">
                      <input type="color" class="form-control color-picker" value="#FF0000">
                      <input type="text" class="form-control color-hex" placeholder="#FF0000" value="FF0000">
                      <button type="button" class="btn btn-danger remove-color">&times;</button>
                    </div>
                  </div>
                  <button type="button" class="btn btn-primary" id="add-color">+ {{ __('services.buttons.add_color') }}</button>
                  <input type="hidden" id="cf_color_values">
                  <div class="input_hint">{{ __('services.messages.apply_only_for') }}:
                    <text class="highlighted_text">{{ __('services.field_types.color_picker') }}</text>.
                  </div>
                </div>

                {{-- ملاحظات --}}
                <div class="col-md-12">
                  <label class="form-label">{{ __('services.labels.notes') }}</label>
                  <textarea id="cf_note" rows="3" class="form-control"></textarea>
                </div>



                {{-- أيقونة الحقل --}}
                <div class="col-md-12">
                  <label class="form-label">{{ __('services.labels.icon') }}</label>
                  <input type="file" id="cf_icon" class="form-control" accept=".jpg,.jpeg,.png,.svg,.webp">
                  <div class="d-flex align-items-center gap-2 mt-2">
                    <div id="cf_icon_preview" class="cf-icon-preview small text-muted">{{ __('services.messages.no_icon_selected') }}</div>
                    <button type="button" class="btn btn-sm btn-outline-danger d-none" id="cf_icon_clear">
                      <i class="bi bi-x-circle"></i> {{ __('services.buttons.remove_icon') }}
                    </button>
                  </div>
                </div>



                <div class="col-md-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="cf_required">
                    <label class="form-check-label" for="cf_required">{{ __('services.labels.required') }}</label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="cf_active" checked>
                    <label class="form-check-label" for="cf_active">{{ __('services.labels.active') }}</label>
                  </div>
                </div>

                <div class="col-md-12 text-end">

                  <button type="button" class="btn btn-light-secondary me-2 d-none" id="btn_cancel_edit">
                    <i class="bi bi-x-circle"></i> {{ __('services.buttons.cancel_edit') }}
                  </button>

                  <button type="button" class="btn btn-primary" id="btn_push_field">
                    <i class="bi bi-plus-circle"></i> {{ __('services.buttons.add_field') }}
                  </button>
                </div>
              </div>
            </div>

            {{-- لائحة الحقول المضافة --}}
            <div class="border rounded p-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>{{ __('services.messages.fields_added') }}</strong>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_clear_fields">
                  <i class="bi bi-trash"></i> {{ __('services.buttons.clear_all') }}
                </button>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="width:24%">{{ __('services.labels.label') }}</th>
                      <th style="width:14%">{{ __('services.labels.type') }}</th>
                      <th style="width:10%">{{ __('services.labels.req') }}</th>
                      <th style="width:12%">{{ __('services.labels.active_question') }}</th>
                      <th style="width:18%">{{ __('services.labels.icon') }}</th>
                      <th style="width:22%">{{ __('services.labels.values_note') }}</th>
                      <th style="width:10%">{{ __('services.labels.actions') }}</th>
                    </tr>
                  </thead>
                  <tbody id="cf_rows">
                    <tr class="cf-empty"><td colspan="7" class="text-center text-muted">{{ __('services.messages.no_custom_fields') }}</td></tr>
                  </tbody>
                </table>
              </div>
              <div class="alert alert-info mt-2 mb-0 p-2">
                <div class="small mb-1">
                  {{ __('services.messages.types_hint') }}
                </div>
                <div class="small">
                  {{ __('services.messages.types_hint_detail') }}
                </div>
              </div>
            </div>
          </div>






          {{-- Direct chat --}}
          <div class="col-12 mb-2"><hr class="my-2"><h6 class="mb-3">{{ __('services.labels.direct_chat_routing') }}</h6></div>

          <div class="col-md-6 mb-3">
            <input type="hidden" name="direct_to_user" value="0">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="direct_to_user" name="direct_to_user" value="1" {{ old('direct_to_user')?'checked':'' }}>
              <label class="form-check-label" for="direct_to_user">{{ __('services.messages.route_to_advertiser') }}</label>
            </div>
            @error('direct_to_user')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <small class="text-muted d-block mt-1">{{ __('services.messages.continue_opens_chat') }}</small>
          </div>

          <div class="col-md-6 mb-3 direct-user-wrap">
            <label for="direct_user_id" class="form-label">{{ __('services.labels.advertiser') }}</label>
            <select id="direct_user_id" name="direct_user_id" class="form-select @error('direct_user_id') is-invalid @enderror">
              <option value="">{{ __('services.labels.select_advertiser') }}</option>
              @foreach(($users ?? []) as $u)
                <option value="{{ $u->id }}" {{ old('direct_user_id')==$u->id?'selected':'' }}>{{ $u->name }} (ID: {{ $u->id }})</option>
              @endforeach
            </select>
            @error('direct_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          {{-- Expiry --}}
          <div class="col-md-6 mb-3">
            <label for="expiry_date" class="form-label">{{ __('services.labels.expiry_date') }}</label>
            <input type="date" class="form-control @error('expiry_date') is-invalid @enderror" id="expiry_date" name="expiry_date" value="{{ old('expiry_date') }}">
            <small class="text-muted">{{ __('services.messages.leave_empty_no_expiry') }}</small>
            @error('expiry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          {{-- Actions --}}
          <div class="col-12">
            <button type="submit" class="btn btn-primary me-1 mb-1">{{ __('services.buttons.submit') }}</button>
            <button type="reset" class="btn btn-light-secondary me-1 mb-1">{{ __('services.buttons.reset') }}</button>
          </div>

        </div>
      </form>
    </div>
  </div>
</section>
@endsection

@section('script')



@php
  $serviceCfConfig = [
      'schema' => old('service_fields_schema'),
      'oldValues' => old('custom_fields', []),
      'existingValues' => [],
      'existingFileUrls' => [],
      'existingIconUrls' => [],
      'existingIconPaths' => [],
      'storageBaseUrl' => asset('storage'),

  ];
@endphp
<script>
  window.__serviceCfConfig = {!! json_encode($serviceCfConfig, JSON_UNESCAPED_UNICODE) !!};
</script>
@include('services.partials.custom-fields-script')




<script>
$(function(){
  if (typeof tinymce !== 'undefined') {
    tinymce.init({
      selector: '#tinymce_editor',
      height: 300,
      menubar: false,
      plugins: ['advlist autolink lists link image charmap print preview anchor','searchreplace visualblocks code fullscreen','insertdatetime media table paste code help wordcount'],
      toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help'
    });
  }

  // Paid & direct toggles
  function togglePaid(){ $('.paid-fields').toggle($('#is_paid').is(':checked')); }
  function toggleDirect(){ $('.direct-user-wrap').toggle($('#direct_to_user').is(':checked')); }
  $('#is_paid').on('change',togglePaid); $('#direct_to_user').on('change',toggleDirect);
  togglePaid(); toggleDirect();


});
</script>


@endsection
