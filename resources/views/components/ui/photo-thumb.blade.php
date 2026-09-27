@props([
    'src',
    'download' => null,
    'caption' => '',
    'when' => '',
    'event' => 'photo-viewer',
])

{{-- A thumbnail that opens the page's photo-viewer with every photo of its
[data-photo-album], in order. The slot is the preview itself. --}}
<button
    type="button"
    {{ $attributes->merge(['class' => 'photo-thumb']) }}
    title="{{ __('assistant.media.enlarge') }}"
    data-photo
    data-src="{{ $src }}"
    data-download="{{ $download ?? $src }}"
    data-caption="{{ $caption }}"
    data-when="{{ $when }}"
    x-data="photoThumb('{{ $event }}')"
    x-on:click="openAlbum()"
>
    {{ $slot }}
</button>
