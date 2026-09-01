@props([
    'label' => null,
    'hint' => null,        // standing description under the field
    'error' => null,       // Laravel error; with none passed it is read from the ErrorBag by `name`
    'alpineError' => null, // key in the Alpine `errors` bag: border and message follow it
    'name' => null,
    'id' => null,
    'accept' => 'image/png,image/webp,image/jpeg,image/svg+xml',
    'preview' => null,     // URL of the stored file, shown until a new one is picked
    'note' => null,        // the small line inside the zone (formats, weight)
    'span' => 'text',      // width BY CONTENT: code | short | text | long | full
])

@php
    $id = $id ?? ($name ? 'if-'.$name : ($label ? 'if-'.\Illuminate\Support\Str::slug($label) : null));
    $error = $error ?? ($name && isset($errors) && $errors->has($name) ? $errors->first($name) : null);

    // The prop takes only the key; the component builds the Alpine expression
    // against the `errors` bag, so no Blade ever writes that expression by hand.
    $alpineErrorExpr = $alpineError !== null ? 'errors.'.$alpineError : null;

    $descId = $id ? $id.'-desc' : null;
    $errId = $id ? $id.'-err' : null;
    $describedBy = trim(($error && $errId ? $errId.' ' : '').($hint && $descId ? $descId : '')) ?: null;

    // A field's width is declared by what the field IS, never in columns:
    // `.catalog-form` hands out the slack, so no row is left ragged on the right.
    // A map and not concatenation, so an invalid value falls back to the default.
    $spanClass = ['code' => 'f-code', 'short' => 'f-short', 'text' => 'f-text',
        'long' => 'f-long', 'full' => 'f-full'][$span] ?? 'f-text';
@endphp

<div class="field {{ $spanClass }}">
    @if ($label)
        <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    @endif

    {{-- The zone is not a <label>: the field already has one above, and a second
    one would read the same name twice. It behaves as the button it looks
    like, and hands the click to the picker. --}}
    <div
        class="field-drop{{ $error ? ' field-error' : '' }}"
        x-data="inputsformFile({ preview: @js($preview) })"
        x-bind:class="{ 'is-dragging': dragging, 'field-error': {{ $alpineErrorExpr ? '!!('.$alpineErrorExpr.')' : 'false' }} }"
        x-on:click="$refs.picker.click()"
        x-on:keydown.enter.prevent="$refs.picker.click()"
        x-on:keydown.space.prevent="$refs.picker.click()"
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="drop($event)"
        role="button"
        tabindex="0"
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
    >
        {{-- `.stop` is what keeps this from looping: the programmatic click on
        the picker would bubble back to the zone and open it again. --}}
        <input
            type="file"
            class="field-drop-picker"
            accept="{{ $accept }}"
            @if ($id) id="{{ $id }}" @endif
            @if ($name) name="{{ $name }}" @endif
            @if ($error) aria-invalid="true" @endif
            x-ref="picker"
            x-on:click.stop
            x-on:change="pick($event)"
            x-on:livewire-upload-start="uploading = true"
            x-on:livewire-upload-finish="uploading = false"
            x-on:livewire-upload-error="uploading = false"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
            {{ $attributes }}
        />

        <img class="field-drop-preview" alt="" x-show="preview" x-cloak x-bind:src="preview" />

        <span class="field-drop-icon" x-show="! preview">
            <x-icon name="upload" :size="22" />
        </span>

        <span x-show="! preview">{{ $slot->isNotEmpty() ? $slot : __('forms.file.upload') }}</span>

        <span class="field-drop-name" x-show="!! name" x-cloak x-text="name"></span>

        @if ($note)
            <span class="field-drop-note" x-show="! preview">{{ $note }}</span>
        @endif

        {{-- The bar only exists while the file travels: a strip permanently
        parked at the bottom of the box reads as part of the frame. --}}
        <span class="field-drop-bar" x-show="uploading" x-cloak>
            <span x-bind:style="`width: ${progress}%`"></span>
        </span>
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
