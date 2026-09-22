@props([
    'label' => null,
    'name' => null,
    'min' => 0,
    'max' => 100,
    'step' => 1,
])

<label class="flex w-full flex-col gap-2">
    @if ($label)
        <span class="text-body font-semibold" style="font-size: var(--text-sm)">{{ $label }}</span>
    @endif
    <input
        type="range"
        {{ $attributes->merge(['class' => 'range-input', 'name' => $name, 'min' => $min, 'max' => $max, 'step' => $step]) }}
    />
</label>
