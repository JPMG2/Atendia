@props([
    'src' => null,
    'name' => '',
    'size' => 'md',     // xs | sm | md | lg | xl
    'status' => null,   // online | away | offline
])

@php
    $dim = ['xs' => 24, 'sm' => 32, 'md' => 40, 'lg' => 52, 'xl' => 72][$size] ?? 40;
    $fontSize = $dim <= 24 ? 10 : ($dim <= 32 ? 12 : ($dim <= 40 ? 15 : ($dim <= 52 ? 19 : 26)));

    $initials = collect(explode(' ', trim($name)))
        ->filter()->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
        ->implode('') ?: '?';

    // Tinte determinístico por nombre (mismos tokens → light/dark solos).
    $tints = [
        ['var(--jade-100)', 'var(--jade-700)'],
        ['var(--coral-100)', 'var(--coral-700)'],
        ['var(--blue-50)', 'var(--blue-500)'],
        ['var(--amber-50)', 'var(--amber-500)'],
    ];
    [$bg, $fg] = $tints[crc32($name) % count($tints)];

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
