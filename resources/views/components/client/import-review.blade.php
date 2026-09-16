@props([
    'headers',
    'labels',
    'mapping',
    'fixOriginals',
    'fixes',
    'totalRows',
    'targetOptions',
])

{{-- The import review, shared by the wizard step and the products screen:
one confirmed mapping per column, the AI's typo fixes as editable
suggestions, and nothing written until Confirmar. --}}
<div class="flex flex-col gap-3">
    <div>
        <h3 class="text-strong font-display text-base font-bold">{{ __('wizard.products.review_title') }}</h3>
        <p class="text-muted text-sm">{{ __('wizard.products.review_hint', ['rows' => $totalRows]) }}</p>
    </div>

    @foreach ($headers as $index => $header)
        <x-catalog.form-row wire:key="map-{{ $index }}">
            <div class="f-long">
                <x-inputsform.input
                    span="full"
                    name="label_{{ $index }}"
                    wire:model="labels.{{ $index }}"
                    :value="$labels[$index] ?? $header"
                />
                @if (($labels[$index] ?? $header) !== $header)
                    <span class="wizard-map-was">{{ __('wizard.products.was', ['column' => $header]) }}</span>
                @endif
            </div>
            <x-inputsform.combobox
                span="short"
                name="map_{{ $index }}"
                wire:model.live="mapping.{{ $index }}"
                :value="$mapping[$index] ?? 'extra'"
                :options="$targetOptions"
            />
        </x-catalog.form-row>
    @endforeach

    @if ($fixOriginals !== [])
        <div class="mt-2">
            <h3 class="text-strong font-display text-base font-bold">{{ __('wizard.products.fixes_title') }}</h3>
            <p class="text-muted text-sm">{{ __('wizard.products.fixes_hint') }}</p>
        </div>

        @foreach ($fixOriginals as $index => $original)
            <x-catalog.form-row wire:key="fix-{{ $index }}">
                <div class="f-long">
                    <x-inputsform.input
                        span="full"
                        name="fix_{{ $index }}"
                        wire:model="fixes.{{ $index }}"
                        :value="$fixes[$index] ?? ''"
                    />
                    <span class="wizard-map-was">{{ __('wizard.products.was', ['column' => $original]) }}</span>
                </div>
            </x-catalog.form-row>
        @endforeach
    @endif

    <div class="mt-1 flex flex-wrap items-center gap-3">
        {{-- Cancel wears the danger colour by the owner's call (2026-09-15). --}}
        <x-ui.button variant="danger" size="sm" wire:click="cancelUpload">
            {{ __('wizard.products.cancel') }}</x-ui.button>
        <span class="flex-1"></span>
        <x-ui.button variant="primary" size="sm" wire:click="confirmImport">
            {{ __('wizard.products.confirm') }}</x-ui.button>
    </div>
</div>
