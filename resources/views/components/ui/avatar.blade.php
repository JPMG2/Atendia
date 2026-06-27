@props([
    'src' => null,
    'name' => '',
    'size' => 'md',     // xs | sm | md | lg | xl
    'status' => null,   // online | away | offline
    'tint' => null,     // brand | coral | info | warning ; null = determinístico por nombre
])

@php
    $dim = ['xs' => 24, 'sm' => 32, 'md' => 40, 'lg' => 52, 'xl' => 72][$size] ?? 40;
    $fontSize = $dim <= 24 ? 10 : ($dim <= 32 ? 12 : ($dim <= 40 ? 15 : ($dim <= 52 ? 19 : 26)));

    $initials = collect(explode(' ', trim($name)))
        ->filter()->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
        ->implode('') ?: '?';

    // Paleta de tintes (mismos tokens → light/dark solos).
    $tints = [
        'brand' => ['var(--jade-100)', 'var(--jade-700)'],
        'coral' => ['var(--coral-100)', 'var(--coral-700)'],
        'info' => ['var(--blue-50)', 'var(--blue-500)'],
        'warning' => ['var(--amber-50)', 'var(--amber-500)'],
    ];
    // Tinte fijo si se pide uno válido; si no, determinístico por nombre.
    $keys = array_keys($tints);
    $tintKey = array_key_exists($tint, $tints) ? $tint : $keys[crc32($name) % count($keys)];
    [$bg, $fg] = $tints[$tintKey];

    $statusColor = ['online' => 'var(--success)', 'away' => 'var(--warning)', 'offline' => 'var(--text-subtle)'][$status] ?? null;
    $statusDim = max(8, (int) round($dim * 0.26));
@endphp

<span {{ $attributes->merge(['class' => 'avatar']) }}>
    @if ($src)
        <img class="avatar-img" src="{{ $src }}" alt="{{ $name }}" style="width:{{ $dim }}px;height:{{ $dim }}px;" />
    @else
        <span class="avatar-fallback" style="width:{{ $dim }}px;height:{{ $dim }}px;background:{{ $bg }};color:{{ $fg }};font-size:{{ $fontSize }}px;">{{ $initials }}</span>
    @endif
    @if ($statusColor)
        <span class="avatar-status" style="width:{{ $statusDim }}px;height:{{ $statusDim }}px;background:{{ $statusColor }};"></span>
    @endif
</span>
