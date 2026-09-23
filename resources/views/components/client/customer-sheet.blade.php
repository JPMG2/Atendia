@props([
    'customer' => null,
    'duplicate' => null,
    'form' => null,
    'show' => false,
])

{{-- The customer fiche, shared by Conversations and the directory. The host
component must use the ManagesCustomerSheet trait: every wire call and the
`customerForm` bindings below are its contract. --}}
@if ($show && $customer !== null)
    <x-ui.slide-over
        x-on:slide-over-close="$wire.closeCustomer()"
        :title="$customer->displayName() ?? __('client.conversations.anonymous')"
        :subtitle="__('client.customers.since', ['date' => $customer->first_seen_at?->format('d/m/Y')])"
    >
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <span class="text-strong font-mono text-sm">{{ $customer->phone }}</span>
                @if ($customer->country_code !== null)
                    <span class="text-muted font-mono text-xs uppercase">{{ $customer->country_code }}</span>
                @endif
                @if ($customer->profile_name !== null)
                    <span class="text-muted text-xs">{{ __('client.customers.push_name', ['name' => $customer->profile_name]) }}</span>
                @endif
            </div>

            @if ($duplicate !== null)
                <x-ui.alert variant="warning" :title="__('client.customers.merge_title')">
                    {{ __('client.customers.merge_body', ['name' => $duplicate->displayName() ?? $duplicate->phone]) }}
                    <div class="mt-2">
                        <x-ui.button
                            variant="secondary"
                            size="sm"
                            x-on:click="dialog.confirm({
                                title: @js(__('client.customers.merge_confirm_title')),
                                message: @js(__('client.customers.merge_confirm_body')),
                                accept: @js(__('client.customers.merge_button')),
                                type: 'danger',
                            }).then((ok) => ok && $wire.mergeCustomer())"
                        >
                            {{ __('client.customers.merge_button') }}
                        </x-ui.button>
                    </div>
                </x-ui.alert>
            @endif

            <x-catalog.form-row>
                <x-inputsform.input
                    span="full"
                    name="name"
                    :label="__('client.customers.field_name')"
                    wire:model="customerForm.name"
                    :value="$form->name"
                />
            </x-catalog.form-row>
            {{-- One per row: the sheet is a narrow slide-over, and side by side
            both fields were too small to read (owner's call, 2026-09-23). --}}
            <x-catalog.form-row>
                <x-inputsform.input
                    span="full"
                    name="email"
                    :label="__('client.customers.field_email')"
                    wire:model="customerForm.email"
                    :value="$form->email"
                />
            </x-catalog.form-row>
            <x-catalog.form-row>
                <x-inputsform.datepicker
                    span="full"
                    name="birthday"
                    :label="__('client.customers.field_birthday')"
                    wire:model="customerForm.birthday"
                    :value="$form->birthday"
                />
            </x-catalog.form-row>
            @foreach (['name', 'email', 'birthday'] as $aiField)
                @continue(! isset($customer->ai_extracted[$aiField]))
                <p class="text-muted -mt-2 flex items-center gap-1.5 text-xs">
                    <x-icon name="bot" :size="14" style="color: var(--brand)" />
                    {{ __('client.customers.ai_badge', ['field' => __('client.customers.field_'.$aiField), 'value' => $customer->ai_extracted[$aiField]['value']]) }}
                </p>
            @endforeach
            <x-catalog.form-row>
                <x-inputsform.textarea
                    span="full"
                    name="notes"
                    :rows="3"
                    :label="__('client.customers.field_notes')"
                    wire:model="customerForm.notes"
                />
            </x-catalog.form-row>

            <div class="bd-subtle border-t pt-3">
                @if ($customer->marketing_opt_in_at !== null)
                    <p class="text-body flex items-center gap-1.5 text-sm">
                        <x-icon name="check" :size="16" style="color: var(--success)" />
                        {{ __('client.customers.opt_in_yes', ['date' => $customer->marketing_opt_in_at->format('d/m/Y')]) }}
                    </p>
                @else
                    @if ($customer->marketing_opt_in_requested_at !== null)
                        <p class="text-muted mb-2 text-xs">
                            {{ __('client.customers.opt_in_requested', ['date' => $customer->marketing_opt_in_requested_at->format('d/m/Y')]) }}
                        </p>
                    @endif
                    <x-ui.button variant="secondary" size="sm" icon="gift" wire:click="requestOptIn">
                        {{ __('client.customers.opt_in_button') }}
                    </x-ui.button>
                @endif
            </div>
        </div>

        <x-slot:footer>
            <x-ui.button variant="danger" size="sm" wire:click="closeCustomer">
                {{ __('client.customers.cancel') }}</x-ui.button>
            <span class="flex-1"></span>
            <x-ui.button variant="primary" size="sm" wire:click="saveCustomer">
                {{ __('client.customers.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.slide-over>
@endif
