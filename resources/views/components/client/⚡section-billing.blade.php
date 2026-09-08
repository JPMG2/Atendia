<?php

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Models\Currency;
use App\Models\TaxCondition;
use App\Services\NotificationService;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Currency and billing card of "Mi negocio". Everything optional: no tax
 * data means the invoice goes out to a natural person, and the reference
 * currency mirrors the Venezuelan "Ref" habit. Reads and saves through the
 * client's main class — the same door the wizard uses.
 */
new class extends Component
{
    use HasNotifications;

    public ?int $currency_id = null;

    public ?int $reference_currency_id = null;

    public ?int $tax_condition_id = null;

    public ?string $tax_id = null;

    public function mount(): void
    {
        $details = $this->client()->taxDetails();

        if ($details === null) {
            return;
        }

        foreach ($details->data() as $field => $value) {
            $this->{$field} = $value;
        }

        // A fresh card suggests the registration country's currency; it only
        // sticks when the client saves.
        $this->currency_id ??= Auth::user()->business?->country?->currency_id;
    }

    /** Rebuilt per request: a Livewire component cannot hold it in a constructor. */
    protected function client(): Client
    {
        return Client::for(Auth::user());
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'reference_currency_id' => ['nullable', 'integer', 'different:currency_id', Rule::exists('currencies', 'id')->where('is_active', true)],
            // Scoped to the business's country: an id posted by hand must not
            // cross borders.
            'tax_condition_id' => ['nullable', 'integer', Rule::exists('tax_conditions', 'id')->where('country_id', Auth::user()?->business?->country_id)],
            'tax_id' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'currency_id' => __('client.business.billing.currency'),
            'reference_currency_id' => __('client.business.billing.reference'),
            'tax_condition_id' => __('client.business.billing.tax_condition'),
            'tax_id' => __('client.business.billing.tax_id'),
        ];
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function currencyOptions(): array
    {
        return Currency::options([true]);
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    #[Computed]
    public function taxConditionOptions(): array
    {
        return TaxCondition::options([true], countryId: Auth::user()?->business?->country_id);
    }

    public function save(): void
    {
        $details = $this->client()->taxDetails();

        if ($details === null) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return;
        }

        $validated = $this->validate();

        $business = $details->save($validated);

        $this->dispatchNotification(resolve(NotificationService::class)->notificationFor($business, 'updated'));
    }
};
?>

<x-ui.card class="bp-card" data-section="facturacion">
    <div class="bp-card-head">
        <h2>{{ __('client.business.billing.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('client.business.billing.sub') }}</p>

    <div class="bp-form">
        <x-catalog.form-row>
            <x-inputsform.combobox span="text" :label="__('client.business.billing.currency')" name="currency_id"
                :options="$this->currencyOptions" :value="$currency_id" wire:model="currency_id" />
            <x-inputsform.combobox span="text" :label="__('client.business.billing.reference').' · '.__('client.business.optional')"
                name="reference_currency_id" :options="$this->currencyOptions" :value="$reference_currency_id"
                wire:model="reference_currency_id" :hint="__('client.business.billing.reference_hint')" />
        </x-catalog.form-row>
        <x-catalog.form-row>
            <x-inputsform.combobox span="text" :label="__('client.business.billing.tax_condition').' · '.__('client.business.optional')"
                name="tax_condition_id" :options="$this->taxConditionOptions" :value="$tax_condition_id"
                wire:model="tax_condition_id" :placeholder="__('client.business.billing.tax_condition_placeholder')" />
            <x-inputsform.input span="text" :label="__('client.business.billing.tax_id').' · '.__('client.business.optional')"
                name="tax_id" wire:model="tax_id" :placeholder="__('client.business.billing.tax_id_placeholder')" class="font-mono" />
        </x-catalog.form-row>
    </div>
    <p class="bp-hint">{{ __('client.business.billing.natural_hint') }}</p>

    <div class="bp-card-actions">
        <x-ui.button variant="primary" size="sm" wire:click="save">{{ __('client.business.actions.save') }}</x-ui.button>
    </div>
</x-ui.card>
