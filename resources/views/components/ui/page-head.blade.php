@props([
    'title',
    'sub' => null,
    'back' => null,      // URL of the back arrow; null hides it
    'backLabel' => null, // aria-label of the back arrow
])

{{-- One head for every screen: the default slot sits at the far side (badges,
buttons), `lead` before the title (an avatar) and `inline` right beside it. --}}
<div {{ $attributes->merge(['class' => 'page-head']) }}>
    <div @class(['bp-head' => $back !== null || isset($lead)])>
        @if ($back !== null)
            <a href="{{ $back }}" wire:navigate class="bp-back" aria-label="{{ $backLabel }}">
                <x-icon name="chevron-left" :size="18" />
            </a>
        @endif
        {{ $lead ?? '' }}
        <div>
            @isset($inline)
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="page-head-title">{{ $title }}</h1>
                    {{ $inline }}
                </div>
            @else
                <h1 class="page-head-title">{{ $title }}</h1>
            @endisset
            @if ($sub !== null)
                <p class="page-head-sub">{{ $sub }}</p>
            @endif
        </div>
    </div>
    {{ $slot }}
</div>
