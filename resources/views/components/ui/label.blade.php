@props(['for' => null])

<label @if ($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'field-label']) }}>
    {{ $slot }}
</label>
