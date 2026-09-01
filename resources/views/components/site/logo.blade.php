@props(['size' => 24, 'href' => '#top', 'light' => null, 'dark' => null])

@php
    // The stored mark wins over the bundled asset: the footer has to show the
    // company that was configured, not the one that shipped with the code. With
    // only one saved it stands in for both themes, which beats showing none.
    $lightSrc = $light ? asset('storage/'.$light) : asset('assets/logo-mark.svg');
    $darkSrc = $dark ? asset('storage/'.$dark) : $lightSrc;
    $box = 'width: '.($size + 8).'px; height: '.($size + 8).'px;';
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <img class="logo-mark-light" src="{{ $lightSrc }}" alt="" style="{{ $box }}">
    <img class="logo-mark-dark" src="{{ $darkSrc }}" alt="" style="{{ $box }}">
    <span class="font-display" style="font-weight:800; font-size: {{ $size }}px; letter-spacing:-0.03em; color:var(--text-strong);">
        Atend<span class="text-brand">ia</span>
    </span>
</a>
