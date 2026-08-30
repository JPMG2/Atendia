@props([
    'steps' => [],       // [['value'=>'main','label'=>'Paso','desc'=>'Qué se carga acá','icon'=>?], ...]
    'default' => null,
    'unlocked' => false, // true = el registro ya existe → todos los pasos abiertos
    'lockedHint' => null,
])

@php
    $active = $default ?? ($steps[0]['value'] ?? null);
@endphp

{{-- A step is NOT a tab: besides switching panels it says how far along the
person is and which one they cannot open yet.

The first step is what opens the rest: while locked only it can be
touched, and unlocking marks it done. The state lives in Alpine and is
opened from outside with an event. --}}
<div
    x-data="{
        step: @js($active),
        unlocked: @js((bool) $unlocked),
        values: @js(array_column($steps, 'value')),
        isLocked(index) { return index > 0 && ! this.unlocked },
        isDone(index) { return index === 0 && this.unlocked },
        // Avisa hacia arriba SOLO cuando el paso cambia de verdad: quien escuche
        // (el bag de errores de la pantalla) no tiene por qué vaciarse porque
        // alguien volvió a clickear el paso en el que ya estaba parado.
        open(value, index) {
            if (this.isLocked(index) || this.step === value) { return }

            this.step = value
            this.$dispatch('step-changed', { step: value })
        },

        // Abre el resto de los pasos y lleva al siguiente. Si ya estaba abierto
        // no hace nada: volver a guardar el paso 1 no tiene por qué sacar al
        // usuario de donde está parado.
        unlock() {
            if (this.unlocked) { return }

            this.unlocked = true
            this.step = this.values[this.values.indexOf(this.step) + 1] ?? this.step
        },
    }"
    x-on:stepper-unlock.window="unlock()"
    {{ $attributes }}
>
    <ol class="stepper" role="tablist">
        @foreach ($steps as $index => $step)
            @if ($index > 0)
                {{-- The thread is the only thing saying one step leads to the
                next: grey until the previous one saved, brand when it
                did. --}}
                <li class="stepper-link" :class="{ 'is-done': unlocked }" aria-hidden="true"></li>
            @endif

            <li class="stepper-item">
                <button
                    type="button"
                    role="tab"
                    class="stepper-step"
                    :class="{
                        'is-current': step === @js($step['value']),
                        'is-done': isDone({{ $index }}),
                        'is-locked': isLocked({{ $index }}),
                    }"
                    :aria-selected="step === @js($step['value'])"
                    :aria-disabled="isLocked({{ $index }})"
                    x-on:click="open(@js($step['value']), {{ $index }})"
                >
                    {{-- The disc carries the number while pending, the tick once the
                    step is saved and the padlock while it cannot be
                    opened: the state reads without a word. --}}
                    <span class="stepper-mark">
                        <span x-show="! isDone({{ $index }}) && ! isLocked({{ $index }})">{{ $index + 1 }}</span>
                        <span x-show="isDone({{ $index }})" x-cloak><x-icon name="check" :size="18" /></span>
                        <span x-show="isLocked({{ $index }})" x-cloak><x-icon name="lock" :size="15" /></span>
                    </span>

                    <span class="stepper-text">
                        <span class="stepper-label">{{ $step['label'] }}</span>

                        @isset($step['desc'])
                            <span class="stepper-desc" x-show="! isLocked({{ $index }})">{{ $step['desc'] }}</span>
                        @endisset

                        {{-- Locked is not enough: it has to say WHAT opens it. --}}
                        @if ($lockedHint)
                            <span class="stepper-hint" x-show="isLocked({{ $index }})" x-cloak>{{ $lockedHint }}</span>
                        @endif
                    </span>
                </button>
            </li>
        @endforeach
    </ol>

    {{ $slot }}
</div>
