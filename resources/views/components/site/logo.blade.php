@props(['size' => 24, 'href' => '#top'])

@php
    // The stored mark wins over the bundled asset on EVERY surface: header,
    // footer and auth all show the company that was configured, not the one
    // that shipped. With only one theme saved that file stands in for both.
    $company = \App\Models\Company::current();

    $lightSrc = $company?->logo_path_light ? asset('storage/'.$company->logo_path_light) : asset('assets/logo-mark.svg');
    $darkSrc = $company?->logo_path_dark ? asset('storage/'.$company->logo_path_dark) : $lightSrc;
    $box = 'width: '.($size + 8).'px; height: '.($size + 8).'px;';
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <img class="logo-mark-light" src="{{ $lightSrc }}" alt="" style="{{ $box }}">
    <img class="logo-mark-dark" src="{{ $darkSrc }}" alt="" style="{{ $box }}">
    <span class="font-display" style="font-weight:800; font-size: {{ $size }}px; letter-spacing:-0.03em; color:var(--text-strong);">
        Atend<span class="text-brand">ia</span>
    </span>
</a>
