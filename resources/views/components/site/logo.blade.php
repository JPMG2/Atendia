@props(['size' => 24, 'href' => '#top'])

@php
    // The stored mark wins over the bundled asset on EVERY surface: header,
    // footer and auth all show the company that was configured, not the one
    // that shipped. With only one theme saved that file stands in for both.
    ['light' => $lightSrc, 'dark' => $darkSrc] = \App\Models\Company::logoUrls();
    $box = 'width: '.($size + 20).'px; height: '.($size + 20).'px;';
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <img class="logo-mark-light" src="{{ $lightSrc }}" alt="" style="{{ $box }}" />
    <img class="logo-mark-dark" src="{{ $darkSrc }}" alt="" style="{{ $box }}" />
    <span
        class="font-display"
        style="font-weight:800; font-size: {{ $size }}px; letter-spacing:-0.03em; color:var(--text-strong);"
        ><x-site.wordmark accent="var(--brand)"
    /></span>
</a>
