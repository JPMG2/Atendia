@props([
    'form' => null,
    'show' => false,
])

{{-- The teach-the-assistant sheet, shared by "Mi asistente" and Conversaciones.
The host component must expose an AssistantFaqForm as `form` plus the
`saveFaq` and `closeSheet` wire actions: they are its contract. --}}
@if ($show)
    <x-ui.slide-over
        x-on:slide-over-close="$wire.closeSheet()"
        :title="$form->editingId !== null ? __('client.assistant.sheet_edit') : __('client.assistant.sheet_new')"
        :subtitle="__('client.assistant.sheet_hint')"
    >
        <div class="flex flex-col gap-4">
            <x-catalog.form-row>
                <x-inputsform.input
                    span="full"
                    name="question"
                    :label="__('client.assistant.field_question')"
                    :placeholder="__('client.assistant.question_placeholder')"
                    wire:model="form.question"
                    :value="$form->question"
                />
            </x-catalog.form-row>
            <x-catalog.form-row>
                <x-inputsform.textarea
                    span="full"
                    name="answer"
                    :rows="4"
                    :label="__('client.assistant.field_answer')"
                    :hint="__('client.assistant.answer_hint')"
                    wire:model="form.answer"
                >
                    {{ $form->answer }}</x-inputsform.textarea>
            </x-catalog.form-row>
        </div>

        <x-slot:footer>
            <x-ui.button variant="danger" size="sm" wire:click="closeSheet">
                {{ __('client.assistant.sheet_cancel') }}</x-ui.button>
            <span class="flex-1"></span>
            <x-ui.button variant="primary" size="sm" wire:click="saveFaq">
                {{ __('client.assistant.sheet_save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.slide-over>
@endif
