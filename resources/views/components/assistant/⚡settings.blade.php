<?php

use App\Actions\Business\SaveHandoffLevel;
use App\Dto\NotificationDto;
use App\Enums\HandoffLevel;
use App\Enums\NotificationType;
use App\Livewire\Forms\Client\HandoffRulesForm;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * "Configuración" of the assistant: how it BEHAVES while answering. Born
 * with the handoff dial and the owner's own cases; tone and personality
 * will grow here, apart from what it KNOWS (the sibling screen).
 */
new class extends Component
{
    use HasNotifications;

    public HandoffRulesForm $handoffForm;

    /** The handoff dial: eager | balanced | minimal (the owner's insight). */
    public string $handoffLevel = 'balanced';

    public function mount(): void
    {
        $this->handoffLevel = (Auth::user()?->business?->handoff_level ?? HandoffLevel::Balanced)->value;
        $this->handoffForm->setup();
    }

    public function updatedHandoffLevel(string $value): void
    {
        $level = HandoffLevel::tryFrom($value);
        $business = Auth::user()?->business;

        if ($level === null || $business === null) {
            $this->handoffLevel = ($business?->handoff_level ?? HandoffLevel::Balanced)->value;

            return;
        }

        app(SaveHandoffLevel::class)->handle($business, $level);

        $this->dispatchNotification(new NotificationDto(__('client.assistant.handoff_saved'), NotificationType::Success));
    }

    public function saveHandoffRules(): void
    {
        $this->dispatchNotification($this->handoffForm->save());
    }

    /** The tab title comes from translations; a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('client.assistant.settings_title'));
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('client.assistant.settings_title') }}</h1>
            <p class="page-head-sub">{{ __('client.assistant.settings_sub') }}</p>
        </div>
    </div>

    {{-- The owner's dial: a doctor is not a realtor (her insight, 2026-09-20). --}}
    <x-ui.card class="p-5">
        <h2 class="font-display text-strong text-base">{{ __('client.assistant.handoff_title') }}</h2>
        <p class="text-muted mt-0.5 text-sm">{{ __('client.assistant.handoff_sub') }}</p>

        <div class="mt-3 flex flex-col gap-4">
            <x-catalog.form-row>
                <x-inputsform.combobox
                    span="long"
                    name="handoff_level"
                    wire:model.live="handoffLevel"
                    :value="$handoffLevel"
                    :options="[
                        'eager' => __('client.assistant.handoff_levels.eager'),
                        'balanced' => __('client.assistant.handoff_levels.balanced'),
                        'minimal' => __('client.assistant.handoff_levels.minimal'),
                    ]"
                />
            </x-catalog.form-row>
            {{-- The owner's own cases, one per line; they ALWAYS escalate. --}}
            <x-catalog.form-row>
                <div class="f-full">
                    <x-ui.textarea
                        name="rules"
                        :rows="3"
                        :label="__('client.assistant.handoff_rules_label')"
                        :hint="__('client.assistant.handoff_rules_hint')"
                        :placeholder="__('client.assistant.handoff_rules_placeholder')"
                        wire:model="handoffForm.rules"
                    >{{ $handoffForm->rules }}</x-ui.textarea>
                </div>
            </x-catalog.form-row>
            <div class="flex justify-end">
                <x-ui.button variant="primary" size="sm" wire:click="saveHandoffRules">
                    {{ __('client.assistant.handoff_rules_save') }}
                </x-ui.button>
            </div>
        </div>
    </x-ui.card>
</div>
