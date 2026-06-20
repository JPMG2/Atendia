@props([
    'name' => null,
    'label' => null,
    'description' => null,
    'value' => '1',
    'checked' => false,
    'id' => null,
])

<label class="checkbox @if ($description) checkbox-top @endif">
    <input
        type="checkbox"
        @if ($id) id="{{ $id }}" @endif
        @if ($name) name="{{ $name }}" @endif
        value="{{ $value }}"
        @checked($checked)
        {{ $attributes }}
    />
    <span class="checkbox-box">
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
            <path d="M2.5 6.5L5 9L9.5 3.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </span>
    @if ($label || $description)
        <span class="flex flex-col gap-0.5">
            @if ($label)<span class="checkbox-title">{{ $label }}</span>@endif
            @if ($description)<span class="checkbox-desc">{{ $description }}</span>@endif
        </span>
    @endif
</label>
