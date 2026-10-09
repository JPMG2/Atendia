@props([
    'label' => null,
    'hint' => null,
    'error' => null,       // Laravel error; with none passed it is read from the ErrorBag by `name`
    'alpineError' => null, // key in the Alpine `errors` bag: border and message follow it
    'name' => null,
    'id' => null,
    'span' => 'short',     // width BY CONTENT: code | short | text | long | full
    'placeholder' => null,
    'countries' => [],     // [['code' => '54', 'flag' => '🇦🇷'], ...] straight from the catalog
    'value' => null,       // the stored composite: "+54 3415124408"
    'defaultDial' => null, // preselected dial when the value carries none
])

@php
    $id = $id ?? ($name ? 'if-'.$name : ($label ? 'if-'.\Illuminate\Support\Str::slug($label) : null));
    $error = $error ?? ($name && isset($errors) && $errors->has($name) ? $errors->first($name) : null);

    $isRequired = $attributes->has('required') && $attributes->get('required') !== false;

    $alpineErrorExpr = $alpineError !== null ? 'errors.'.$alpineError : null;

    // `wire:model` and friends go on the hidden field, which is the real one;
    // the visible pair carries no `name`, so a native submit never posts it.
    $valueAttributes = $isRequired ? $attributes->except('required') : $attributes;

    // Only what the picker needs, always as strings: the dial is compared as text.
    $options = collect($countries)->map(fn (array $country): array => [
        'code' => (string) $country['code'],
        'flag' => (string) $country['flag'],
        'name' => (string) ($country['name'] ?? ''),
    ])->values()->all();

    $controlClasses = trim('field-control phone-control'.($error ? ' field-error' : ''));

    $descId = $id ? $id.'-desc' : null;
    $errId = $id ? $id.'-err' : null;
    $describedBy = trim(($error && $errId ? $errId.' ' : '').($hint && $descId ? $descId : '')) ?: null;

    $spanClass = ['code' => 'f-code', 'short' => 'f-short', 'text' => 'f-text',
        'long' => 'f-long', 'full' => 'f-full'][$span] ?? 'f-short';
@endphp

<div class="field {{ $spanClass }}">
    @if ($label)
        <label for="{{ $id }}" class="field-label"
            >{{ $label }}
            @if ($isRequired)
                <span class="field-required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div
        class="phone-wrap"
        x-data="inputsformPhone({ value: @js((string) $value), defaultDial: @js((string) $defaultDial), countries: @js($options) })"
        x-on:keydown.escape.stop="closePanel()"
        x-on:click.outside="closePanel()"
    >
        <div class="{{ $controlClasses }}">
            <button
                type="button"
                class="phone-dial"
                tabindex="-1"
                x-on:click="toggle()"
                x-bind:aria-expanded="open"
                aria-haspopup="listbox"
                aria-label="{{ __('forms.phone.country') }}"
            >
                <span x-text="picked ? picked.flag : ''"></span>
                <span class="font-mono">+<span x-text="dial"></span></span>
                <x-icon name="chevron-down" :size="14" />
            </button>

            <input
                type="tel"
                inputmode="tel"
                autocomplete="tel-national"
                x-ref="number"
                x-model="number"
                @if ($id) id="{{ $id }}" @endif
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                @if ($error) aria-invalid="true" @endif
                @if ($alpineErrorExpr) x-bind:aria-invalid="!!({{ $alpineErrorExpr }}) || null" @endif
                @if ($isRequired) aria-required="true" @endif
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                class="field-input phone-number"
            />

            <input
                type="hidden"
                x-ref="real"
                @if ($name) name="{{ $name }}" @endif
                value="{{ $value }}"
                {{ $valueAttributes->whereStartsWith('wire:') }}
            />
        </div>

        {{-- The panel is ours: it scrolls freely and nothing is chosen until a click or Enter. --}}
        <div class="phone-panel" x-show="open" x-cloak>
            <input
                type="text"
                class="phone-search"
                autocomplete="off"
                role="combobox"
                aria-expanded="true"
                x-ref="search"
                x-model="query"
                placeholder="{{ __('forms.phone.search') }}"
                aria-label="{{ __('forms.phone.search') }}"
                x-on:input="highlighted = 0"
                x-on:keydown.arrow-down.prevent="move(1)"
                x-on:keydown.arrow-up.prevent="move(-1)"
                x-on:keydown.enter.prevent="chooseHighlighted()"
                x-on:keydown.tab="closePanel()"
            />

            <ul class="phone-list" x-ref="list" role="listbox" aria-label="{{ __('forms.phone.country') }}">
                <template x-for="(country, index) in filtered()" :key="country.code + country.name">
                    <li
                        class="combo-option"
                        role="option"
                        x-bind:data-active="index === highlighted"
                        x-bind:aria-selected="isPicked(country)"
                        x-on:mousedown.prevent="choose(country)"
                        x-on:mousemove="highlighted = index"
                    >
                        <span class="phone-option-name">
                            <span x-text="country.flag"></span>
                            <span x-text="country.name"></span>
                        </span>
                        <span class="text-muted font-mono" x-text="'+' + country.code"></span>
                    </li>
                </template>

                <li class="combo-empty" x-show="filtered().length === 0">{{ __('forms.combobox.empty') }}</li>
            </ul>
        </div>
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
