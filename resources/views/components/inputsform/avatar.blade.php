@props([
    'label' => null,
    'note' => null,        // formats and weight, under the buttons
    'error' => null,       // Laravel error; with none passed it is read from the ErrorBag by `name`
    'name' => null,
    'model' => null,       // the wire:model path the cropped image uploads to (e.g. form.avatar_file)
    'preview' => null,     // URL of the stored photo
    'initials' => '',      // what the circle shows without a photo
    'span' => 'full',      // width BY CONTENT: code | short | text | long | full
    'removable' => false,
    'accept' => 'image/png,image/webp,image/jpeg',
])

@php
    $error = $error ?? ($name && isset($errors) && $errors->has($name) ? $errors->first($name) : null);
    $spanClass = ['code' => 'f-code', 'short' => 'f-short', 'text' => 'f-text',
        'long' => 'f-long', 'full' => 'f-full'][$span] ?? 'f-full';
@endphp

{{-- Slack/Notion-style photo picker: pick, frame it in a square cropper,
and only the cropped square travels to the server. --}}
<div class="field {{ $spanClass }}">
    @if ($label)
        <span class="field-label">{{ $label }}</span>
    @endif

    <div
        class="avatar-field"
        x-data="inputsformAvatar({ preview: @js($preview), model: @js($model), field: @js($name) })"
        @if ($removable) x-on:file-reset.window="$event.detail.name === field && reset()" @endif
    >
        <div class="avatar-field-row">
            <span class="avatar-field-face" aria-hidden="true">
                <img alt="" x-show="preview" x-cloak x-bind:src="preview" />
                <span x-show="! preview">{{ $initials }}</span>
            </span>

            <div class="avatar-field-actions">
                <div class="flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" size="sm" icon="upload" x-on:click="$refs.picker.click()">
                        {{ __('forms.avatar.pick') }}</x-ui.button>
                    @if ($removable)
                        <x-ui.button
                            variant="ghost"
                            size="sm"
                            icon="trash-2"
                            x-show="preview"
                            x-cloak
                            x-on:click="remove()"
                        >
                            {{ __('forms.avatar.remove') }}</x-ui.button>
                    @endif
                </div>
                @if ($note)
                    <span class="field-hint">{{ $note }}</span>
                @endif
            </div>

            <input
                type="file"
                class="field-drop-picker"
                accept="{{ $accept }}"
                @if ($name) name="{{ $name }}" @endif
                x-ref="picker"
                x-on:change="pick($event)"
            />
        </div>

        <div class="avatar-field-crop" x-show="cropping" x-cloak>
            <div class="avatar-field-stage"><img x-ref="stage" alt="" /></div>
            <p class="field-hint">{{ __('forms.avatar.crop_hint') }}</p>
            <div class="flex flex-wrap justify-end gap-2">
                <x-ui.button variant="danger" size="sm" x-on:click="cancel()">
                    {{ __('forms.avatar.cancel') }}</x-ui.button>
                <x-ui.button variant="primary" size="sm" x-on:click="apply()" x-bind:disabled="uploading">
                    {{ __('forms.avatar.apply') }}</x-ui.button>
            </div>
        </div>
    </div>

    @if ($error)
        <div class="field-meta"><span class="field-error-text">{{ $error }}</span></div>
    @endif
</div>
