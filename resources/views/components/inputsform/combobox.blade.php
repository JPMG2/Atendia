@props([
    'label' => null,
    'hint' => null,        // descripción persistente bajo el campo
    'error' => null,       // error de Laravel; si no se pasa, se toma del ErrorBag por `name`
    'alpineError' => null, // clave del bag Alpine `errors` (ej. "currency_id"): borde y mensaje siguen a ese estado
    'name' => null,
    'id' => null,
    'size' => 'm',         // s | m | l
    'options' => [],       // ['a' => 'Label'] | ['a','b'] | [['value'=>..,'label'=>..]]
    'value' => null,       // opción preseleccionada (su `value`)
    'placeholder' => null,
    'empty' => null,       // texto cuando la búsqueda no encuentra nada
    'loading' => null,     // propiedad Livewire de la que depende la lista (ej. "form.data.country_id"):
                           // mientras ese request viaja el campo queda bloqueado con un spinner
    'span' => 'text',      // ancho POR CONTENIDO: code | short | text | long | full
])

@php
    $id = $id ?? ($name ? 'if-'.$name : ($label ? 'if-'.\Illuminate\Support\Str::slug($label) : null));
    $error = $error ?? ($name && isset($errors) && $errors->has($name) ? $errors->first($name) : null);

    $sizeClass = ['s' => 'field-sm', 'l' => 'field-lg'][$size] ?? '';
    $iconSize = ['s' => 16, 'l' => 20][$size] ?? 18;

    $isDisabled = $attributes->has('disabled') && $attributes->get('disabled') !== false;
    $isRequired = $attributes->has('required') && $attributes->get('required') !== false;

    // La prop recibe solo la clave; el componente arma la expresión Alpine contra
    // el bag `errors` (convención de validate()). Así el Blade nunca escribe la expresión.
    $alpineErrorExpr = $alpineError !== null ? 'errors.'.$alpineError : null;

    // Normaliza las tres formas de `options` a [['value'=>..,'label'=>..]], que es
    // lo único que entiende el JS. El Blade no debe reproducir esta lógica.
    $normalizedOptions = collect($options)
        ->map(fn ($option, $key) => is_array($option)
            ? ['value' => $option['value'], 'label' => (string) $option['label']]
            : (is_string($key)
                ? ['value' => $key, 'label' => (string) $option]
                : ['value' => $option, 'label' => (string) $option]))
        ->values()
        ->all();

    // `wire:model` y compañía van al hidden (es el campo real); el buscador visible
    // no lleva `name` para que un submit nativo no mande el texto tipeado.
    $valueAttributes = $isRequired ? $attributes->except('required') : $attributes;

    $controlClasses = trim('field-control combo-control '.$sizeClass
        .($error ? ' field-error' : '')
        .($isDisabled ? ' is-disabled' : ''));

    // Una lista que depende de otro campo NO se puede tocar mientras la está
    // esperando: elegir sobre la lista vieja guarda un valor que el server ya
    // descartó. `wire:target` acota el bloqueo a ESE campo, así cualquier otro
    // request de la pantalla no congela el combobox de rebote.
    $loadingAttributes = $loading !== null ? 'wire:target="'.$loading.'"' : '';

    $descId = $id ? $id.'-desc' : null;
    $errId = $id ? $id.'-err' : null;
    $listId = $id ? $id.'-list' : null;
    $describedBy = trim(($error && $errId ? $errId.' ' : '').($hint && $descId ? $descId : '')) ?: null;

    // El ancho de un campo se declara por lo que el campo ES, nunca en columnas:
    // `.catalog-form` reparte el sobrante y así ninguna fila queda ragged a la
    // derecha. Mapa (no concatenación) para que un valor inválido caiga al default.
    $spanClass = ['code' => 'f-code', 'short' => 'f-short', 'text' => 'f-text',
        'long' => 'f-long', 'full' => 'f-full'][$span] ?? 'f-text';
@endphp

<div class="field combo {{ $spanClass }}" x-data="inputsformCombobox({ options: {{ \Illuminate\Support\Js::from($normalizedOptions) }}, initial: {{ \Illuminate\Support\Js::from($value) }} })"
    x-on:keydown.escape.stop="closePanel()" x-on:click.outside="closePanel()">
    @if ($label)
        <label for="{{ $id }}" class="field-label">{{ $label }}@if ($isRequired)<span class="field-required" aria-hidden="true">*</span>@endif</label>
    @endif

    <div class="combo-shell">
        <div class="{{ $controlClasses }}" x-bind:class="open && 'is-open'"
            @if ($loading) wire:loading.class="is-loading" {!! $loadingAttributes !!} @endif>
            <input
                type="text"
                role="combobox"
                autocomplete="off"
                @if ($id) id="{{ $id }}" @endif
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                @if ($isDisabled) disabled @endif
                @if ($error) aria-invalid="true" @endif
                @if ($alpineErrorExpr) x-bind:aria-invalid="!!({{ $alpineErrorExpr }}) || null" @endif
                @if ($isRequired) aria-required="true" @endif
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                @if ($listId) aria-controls="{{ $listId }}" @endif
                aria-autocomplete="list"
                x-ref="search"
                x-bind:aria-expanded="open"
                x-model="query"
                x-on:input="onInput()"
                x-on:focus="openPanel()"
                x-on:blur="closePanel()"
                x-on:keydown.arrow-down.prevent="move(1)"
                x-on:keydown.arrow-up.prevent="move(-1)"
                x-on:keydown.enter.prevent="chooseHighlighted()"
                x-on:keydown.tab="closePanel()"
                @if ($loading) wire:loading.attr="disabled" {!! $loadingAttributes !!} @endif
                class="field-input"
            />

            {{-- Clear in one go. It only appears when there is something to clear:
            a fixed cross over an empty field is noise. `mousedown.prevent`
            because the search box's blur closes the panel and would eat
            the click. --}}
            <button type="button" class="combo-clear" tabindex="-1"
                x-show="selected || query" x-cloak
                aria-label="{{ __('forms.combobox.clear') }}"
                @if ($isDisabled) disabled @endif
                @if ($loading) wire:loading.attr="disabled" {!! $loadingAttributes !!} @endif
                x-on:mousedown.prevent="clear()">
                <x-icon name="x" :size="$iconSize - 2" />
            </button>

            <button type="button" class="combo-toggle" tabindex="-1" aria-hidden="true"
                @if ($isDisabled) disabled @endif
                @if ($loading) wire:loading.attr="disabled" {!! $loadingAttributes !!} @endif
                x-on:mousedown.prevent="open ? closePanel() : $refs.search.focus()">
                @if ($loading)
                    <span wire:loading.remove {!! $loadingAttributes !!}><x-icon name="chevron-down" :size="$iconSize" /></span>
                    <span class="combo-spinner" wire:loading {!! $loadingAttributes !!} role="status"
                        aria-label="{{ __('forms.combobox.loading') }}"><x-icon name="loader-circle" :size="$iconSize" /></span>
                @else
                    <x-icon name="chevron-down" :size="$iconSize" />
                @endif
            </button>

            {{-- The real value: it carries the wire:model and travels to the server. --}}
            <input type="hidden" x-ref="value" @if ($name) name="{{ $name }}" @endif
                {{ $valueAttributes }} />
        </div>

        <ul class="combo-list" x-ref="list" x-show="open" x-cloak role="listbox"
            @if ($listId) id="{{ $listId }}" @endif
            @if ($label) aria-label="{{ $label }}" @endif>
            <template x-for="(option, index) in filtered()" :key="option.value">
                <li class="combo-option" role="option"
                    x-bind:data-active="index === highlighted"
                    x-bind:aria-selected="isSelected(option)"
                    x-on:mousedown.prevent="choose(option)"
                    x-on:mousemove="highlighted = index">
                    <span x-text="option.label"></span>
                    <span class="combo-tick" x-show="isSelected(option)"><x-icon name="check" :size="16" /></span>
                </li>
            </template>

            <li class="combo-empty" x-show="filtered().length === 0">{{ $empty ?? __('forms.combobox.empty') }}</li>
        </ul>
    </div>

    {{-- Hint and error stack in .field-meta so they NEVER overlap. --}}
    @if ($hint || $error || $alpineErrorExpr)
        <div class="field-meta">
            @if ($hint)
                <span @if ($descId) id="{{ $descId }}" @endif class="field-hint">{{ $hint }}</span>
            @endif

            @if ($error)
                <span @if ($errId) id="{{ $errId }}" @endif class="field-error-text">{{ $error }}</span>
            @elseif ($alpineErrorExpr)
                <span @if ($errId) id="{{ $errId }}" @endif class="field-error-text" x-show="!!({{ $alpineErrorExpr }})" x-text="{{ $alpineErrorExpr }}" x-cloak></span>
            @endif
        </div>
    @endif
</div>
