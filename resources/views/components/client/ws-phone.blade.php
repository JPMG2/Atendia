@props([
    'name',          // business name shown in the chat header
    'empty' => null, // canvas placeholder while there is nothing to paint
])

{{-- The landing's phone mock: dark bezel, chat header and live canvas.
The conversation is painted by ws-phone.js (wire:ignore: a morph would
wipe it); shared by the wizard rail and the dashboard simulator. --}}
<div class="wizard-phone-frame">
    <div class="wizard-phone-screen">
        <div class="wizard-phone-top">
            <span class="pavatar"><x-icon name="bot" :size="18" /></span>
            <span class="pmeta">
                <span class="pname">{{ $name }}</span>
                <span class="pstatus"><i></i>{{ __('wizard.preview.online') }}</span>
            </span>
        </div>
        <div class="wizard-phone" data-phone wire:ignore data-empty="{{ $empty ?? __('wizard.preview.empty') }}">
            <div class="wizard-phone-empty">{{ $empty ?? __('wizard.preview.empty') }}</div>
        </div>
    </div>
</div>
