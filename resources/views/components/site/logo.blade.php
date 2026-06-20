@props(['size' => 24])

<a href="#top" {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <img src="{{ asset('assets/logo-mark.svg') }}" alt="" style="width: {{ $size + 8 }}px; height: {{ $size + 8 }}px;">
    <span class="font-display" style="font-weight:800; font-size: {{ $size }}px; letter-spacing:-0.03em; color:var(--text-strong);">
        Atend<span class="text-brand">ia</span>
    </span>
</a>
