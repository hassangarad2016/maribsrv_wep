@extends('layouts.main')

@section('title')
    {{ __('Ê«ÃÂ… «· ÿ»Ìﬁ') }}
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
    <section class="section">
        <form class="create-form-without-reset" action="{{ route('settings.store') }}" method="post" data-success-function="successFunction">
            @csrf
            @php
                $sectionStates = $interfaceSettings['sections'] ?? [];
                $categoryStates = $interfaceSettings['categories'] ?? [];
            @endphp

            @foreach ($sectionGroups as $groupTitle => $sections)
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="divider pt-3">
                            <h6 class="divider-text">{{ $groupTitle }}</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>«·⁄‰’—</th>
                                        <th class="text-center">≈ŸÂ«—</th>
                                        <th class="text-center"> ›⁄Ì·</th>
                                        <th>—”«·… «·≈Ìﬁ«› «·„ƒﬁ </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sections as $sectionKey => $label)
                                        @php
                                            $state = $sectionStates[$sectionKey] ?? [];
                                            $visible = (int) old("app_interface.sections.$sectionKey.visible", $state['visible'] ?? 1);
                                            $enabled = (int) old("app_interface.sections.$sectionKey.enabled", $state['enabled'] ?? 1);
                                            $message = old("app_interface.sections.$sectionKey.message", $state['message'] ?? '');
                                        @endphp
                                        <tr>
                                            <td class="fw-bold">{{ $label }}</td>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-inline-flex">
                                                    <input type="hidden" name="app_interface[sections][{{ $sectionKey }}][visible]" class="checkbox-toggle-switch-input" value="{{ $visible ? 1 : 0 }}">
                                                    <input class="form-check-input checkbox-toggle-switch" type="checkbox" role="switch" id="section_{{ $sectionKey }}_visible" {{ $visible ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="section_{{ $sectionKey }}_visible"></label>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-inline-flex">
                                                    <input type="hidden" name="app_interface[sections][{{ $sectionKey }}][enabled]" class="checkbox-toggle-switch-input" value="{{ $enabled ? 1 : 0 }}">
                                                    <input class="form-check-input checkbox-toggle-switch" type="checkbox" role="switch" id="section_{{ $sectionKey }}_enabled" {{ $enabled ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="section_{{ $sectionKey }}_enabled"></label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="text" name="app_interface[sections][{{ $sectionKey }}][message]" class="form-control form-control-sm" value="{{ $message }}" placeholder="—”«·…  ŸÂ— ⁄‰œ „Õ«Ê·… «·œŒÊ·">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="card mb-4">
                <div class="card-body">
                    <div class="divider pt-3">
                        <h6 class="divider-text">«·√ﬁ”«„ «·—∆Ì”Ì… («·›∆« )</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>«·ﬁ”„</th>
                                    <th class="text-center">≈ŸÂ«—</th>
                                    <th class="text-center"> ›⁄Ì·</th>
                                    <th>—”«·… «·≈Ìﬁ«› «·„ƒﬁ </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($categories as $category)
                                    @php
                                        $state = $categoryStates[$category->id] ?? [];
                                        $visible = (int) old("app_interface.categories.{$category->id}.visible", $state['visible'] ?? 1);
                                        $enabled = (int) old("app_interface.categories.{$category->id}.enabled", $state['enabled'] ?? 1);
                                        $message = old("app_interface.categories.{$category->id}.message", $state['message'] ?? '');
                                    @endphp
                                    <tr>
                                        <td class="fw-bold">{{ $category->name }}</td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-inline-flex">
                                                <input type="hidden" name="app_interface[categories][{{ $category->id }}][visible]" class="checkbox-toggle-switch-input" value="{{ $visible ? 1 : 0 }}">
                                                <input class="form-check-input checkbox-toggle-switch" type="checkbox" role="switch" id="category_{{ $category->id }}_visible" {{ $visible ? 'checked' : '' }}>
                                                <label class="form-check-label" for="category_{{ $category->id }}_visible"></label>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-inline-flex">
                                                <input type="hidden" name="app_interface[categories][{{ $category->id }}][enabled]" class="checkbox-toggle-switch-input" value="{{ $enabled ? 1 : 0 }}">
                                                <input class="form-check-input checkbox-toggle-switch" type="checkbox" role="switch" id="category_{{ $category->id }}_enabled" {{ $enabled ? 'checked' : '' }}>
                                                <label class="form-check-label" for="category_{{ $category->id }}_enabled"></label>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" name="app_interface[categories][{{ $category->id }}][message]" class="form-control form-control-sm" value="{{ $message }}" placeholder="—”«·…  ŸÂ— ⁄‰œ „Õ«Ê·… «·œŒÊ·">
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center">·«  ÊÃœ √ﬁ”«„ —∆Ì”Ì….</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Õ›Ÿ «· €ÌÌ—« </button>
            </div>
        </form>
    </section>
@endsection
