@props([
    'label' => null,
    'hint' => null,        // standing description under the field
    'error' => null,       // Laravel error; with none passed it is read from the ErrorBag by `name`
    'alpineError' => null, // key in the Alpine `errors` bag (e.g. "currency_id"): border and message follow it
    'name' => null,
    'id' => null,
    'size' => 'm',         // s | m | l
    'options' => [],       // ['a' => 'Label'] | ['a','b'] | [['value'=>..,'label'=>..]]
    'value' => null,       // preselected option (its `value`)
    'placeholder' => null,
    'empty' => null,       // text shown when the search finds nothing
    'loading' => null,     // Livewire property the list hangs off (e.g. "form.data.country_id"):
    // while that request travels the field stays locked behind a spinner
    'span' => 'text',      // width BY CONTENT: code | short | text | long | full
    'multiple' => false,   // several options as chips; the wire:model must name an ARRAY property
])

@php
    $id = $id ?? ($name ? 'if-'.$name : ($label ? 'if-'.\Illuminate\Support\Str::slug($label) : null));
    $error = $error ?? ($name && isset($errors) && $errors->has($name) ? $errors->first($name) : null);

    $sizeClass = ['s' => 'field-sm', 'l' => 'field-lg'][$size] ?? '';
    $iconSize = ['s' => 16, 'l' => 20][$size] ?? 18;

    $isDisabled = $attributes->has('disabled') && $attributes->get('disabled') !== false;
    $isRequired = $attributes->has('required') && $attributes->get('required') !== false;

    // The prop takes only the key; the component builds the Alpine expression
    // against the `errors` bag, so no Blade ever writes that expression by hand.
    $alpineErrorExpr = $alpineError !== null ? 'errors.'.$alpineError : null;

    // Normalises `options` into value/label pairs, the only shape the JS reads.
    // Only a plain list uses its text as the value: [4 => 'Plato'] must send 4.
    $isPlainList = array_is_list(collect($options)->all());
    $normalizedOptions = collect($options)
        ->map(fn ($option, $key) => is_array($option)
            ? ['value' => $option['value'], 'label' => (string) $option['label']]
            : ($isPlainList
                ? ['value' => $option, 'label' => (string) $option]
                : ['value' => $key, 'label' => (string) $option]))
        ->values()
        ->all();

    // `wire:model` and friends go on the hidden field, which is the real one. The
    // visible search box carries no `name`, so a native submit never posts it.
    $valueAttributes = $isRequired ? $attributes->except('required') : $attributes;

    // `multiple` has no hidden field: its value is an array, entangled with the
    // property the wire:model names (and live only when the attribute says so).
    $modelKey = $multiple ? collect($attributes->getAttributes())->keys()->first(fn ($key) => str_starts_with((string) $key, 'wire:model')) : null;
    $modelPath = $modelKey !== null ? (string) $attributes->get($modelKey) : null;
    $xData = $multiple
        ? 'inputsformMultiCombobox({ options: '.\Illuminate\Support\Js::from($normalizedOptions).', values: $wire.$entangle('.\Illuminate\Support\Js::from($modelPath).', '.($modelKey !== null && str_contains((string) $modelKey, '.live') ? 'true' : 'false').') })'
        : 'inputsformCombobox({ options: '.\Illuminate\Support\Js::from($normalizedOptions).', initial: '.\Illuminate\Support\Js::from($value).' })';

    $controlClasses = trim('field-control combo-control '.($multiple ? 'is-multi ' : '').$sizeClass
        .($error ? ' field-error' : '')
        .($isDisabled ? ' is-disabled' : ''));

    // A list hanging off another field cannot be touched while it waits for it:
    // picking from the stale list stores a value the server already dropped.
    // `wire:target` narrows the block to THAT field and to no other request.
    $loadingAttributes = $loading !== null ? 'wire:target="'.$loading.'"' : '';

    $descId = $id ? $id.'-desc' : null;
    $errId = $id ? $id.'-err' : null;
    $listId = $id ? $id.'-list' : null;
    $describedBy = trim(($error && $errId ? $errId.' ' : '').($hint && $descId ? $descId : '')) ?: null;

    // A field's width is declared by what the field IS, never in columns:
    // `.catalog-form` hands out the slack, so no row is left ragged on the right.
    // A map and not concatenation, so an invalid value falls back to the default.
    $spanClass = ['code' => 'f-code', 'short' => 'f-short', 'text' => 'f-text',
        'long' => 'f-long', 'full' => 'f-full'][$span] ?? 'f-text';
@endphp

<div
    class="field combo {{ $spanClass }}"
    x-data="{{ $xData }}"
    x-on:keydown.escape.stop="closePanel()"
    x-on:click.outside="closePanel()"
>
    @if ($label)
        <label for="{{ $id }}" class="field-label"
            >{{ $label }}
            @if ($isRequired)
                <span class="field-required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="combo-shell">
        <div
            class="{{ $controlClasses }}"
            x-bind:class="open && 'is-open'"
            @if ($loading) wire:loading.class="is-loading" {!! $loadingAttributes !!} @endif
        >
            @if ($multiple)
                <template x-for="chip in chosen()" :key="chip.value">
                    <span class="combo-chip">
                        <span x-text="chip.label"></span>
                        <button
                            type="button"
                            class="combo-chip-x"
                            tabindex="-1"
                            x-bind:aria-label="{{ \Illuminate\Support\Js::from(__('forms.combobox.remove')) }} + ' ' + chip.label"
                            @if ($isDisabled) disabled @endif
                            x-on:mousedown.prevent="remove(chip.value)"
                        >
                            <x-icon name="x" :size="12" />
                        </button>
                    </span>
                </template>
            @endif

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
                {{-- The box may be narrower than the choice: the full label is one hover away. --}}
                @unless ($multiple) x-bind:title="selected ? selected.label : null" @endunless
                x-bind:aria-expanded="open"
                x-model="query"
                x-on:input="onInput()"
                x-on:focus="openPanel()"
                x-on:blur="closePanel()"
                x-on:keydown.arrow-down.prevent="move(1)"
                x-on:keydown.arrow-up.prevent="move(-1)"
                x-on:keydown.enter.prevent="chooseHighlighted()"
                x-on:keydown.tab="closePanel()"
                @if ($multiple) x-on:keydown.backspace="removeLast()" @endif
                @if ($loading) wire:loading.attr="disabled" {!! $loadingAttributes !!} @endif
                class="field-input"
            />

            {{-- Clear in one go. It only appears when there is something to clear:
            a fixed cross over an empty field is noise. `mousedown.prevent`
            because the search box's blur closes the panel and would eat
            the click. The chips of `multiple` carry their own cross. --}}
            @unless ($multiple)
                <button
                    type="button"
                    class="combo-clear"
                    tabindex="-1"
                    x-show="selected || query"
                    x-cloak
                    aria-label="{{ __('forms.combobox.clear') }}"
                    @if ($isDisabled) disabled @endif
                    @if ($loading) wire:loading.attr="disabled" {!! $loadingAttributes !!} @endif
                    x-on:mousedown.prevent="clear()"
                >
                    <x-icon name="x" :size="$iconSize - 2" />
                </button>
            @endunless

            <button
                type="button"
                class="combo-toggle"
                tabindex="-1"
                aria-hidden="true"
                @if ($isDisabled) disabled @endif
                @if ($loading) wire:loading.attr="disabled" {!! $loadingAttributes !!} @endif
                x-on:mousedown.prevent="open ? closePanel() : $refs.search.focus()"
            >
                @if ($loading)
                    <span wire:loading.remove {!! $loadingAttributes !!}
                        ><x-icon name="chevron-down" :size="$iconSize"
                    /></span>
                    <span
                        class="combo-spinner"
                        wire:loading
                        {!! $loadingAttributes !!}
                        role="status"
                        aria-label="{{ __('forms.combobox.loading') }}"
                        ><x-icon name="loader-circle" :size="$iconSize"
                    /></span>
                @else
                    <x-icon name="chevron-down" :size="$iconSize" />
                @endif
            </button>

            {{-- The real value: it carries the wire:model and travels to the server. --}}
            @unless ($multiple)
                <input type="hidden" x-ref="value" @if ($name) name="{{ $name }}" @endif {{ $valueAttributes }} />
            @endunless
        </div>

        <ul
            class="combo-list"
            x-ref="list"
            x-show="open"
            x-cloak
            role="listbox"
            @if ($multiple) aria-multiselectable="true" @endif
            @if ($listId) id="{{ $listId }}" @endif
            @if ($label) aria-label="{{ $label }}" @endif
        >
            <template x-for="(option, index) in filtered()" :key="option.value">
                <li
                    class="combo-option"
                    role="option"
                    x-bind:data-active="index === highlighted"
                    x-bind:aria-selected="isSelected(option)"
                    x-on:mousedown.prevent="{{ $multiple ? 'toggle' : 'choose' }}(option)"
                    x-on:mousemove="highlighted = index"
                >
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
                <span
                    @if ($errId) id="{{ $errId }}" @endif
                    class="field-error-text"
                    x-show="!!({{ $alpineErrorExpr }})"
                    x-text="{{ $alpineErrorExpr }}"
                    x-cloak
                ></span>
            @endif
        </div>
    @endif
</div>
