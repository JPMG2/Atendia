<?php

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Services\NotificationService;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Location card of "Mi negocio". The GBP-style premises question folds the
 * address away on "no" — null means never answered, so neither button lights
 * up. Country and province come from the registration and are read-only
 * here. Writes through the identity slice, sending only its own fields.
 */
new class extends Component
{
    use HasNotifications;

    public ?bool $hasPremises = null;

    public ?string $address = null;

    public ?string $city = null;

    public function mount(): void
    {
        $data = $this->client()->personalData()->data();

        $this->hasPremises = $data->has_premises;
        $this->address = $data->address;
        $this->city = $data->city;
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
            'hasPremises' => ['nullable', 'boolean'],
            'address' => ['nullable', 'string', 'min:3', 'max:255'],
            'city' => ['nullable', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'address' => config('nicename.address'),
            'city' => config('nicename.city'),
        ];
    }

    #[Computed]
    public function countryName(): ?string
    {
        return Auth::user()->business?->country?->name;
    }

    #[Computed]
    public function provinceName(): ?string
    {
        return Auth::user()->business?->province?->name;
    }

    public function save(): void
    {
        if (Auth::user()->business === null) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return;
        }

        $validated = $this->validate();

        // The screen speaks camelCase; the slice speaks columns.
        $business = $this->client()->personalData()->saveIdentity([
            'address' => $validated['address'],
            'city' => $validated['city'],
            'has_premises' => $validated['hasPremises'],
        ]);

        $this->dispatchNotification(resolve(NotificationService::class)->notificationFor($business, 'updated'));
    }
};
?>

<x-ui.card class="bp-card" data-section="ubicacion">
    <div class="bp-card-head">
        <h2>{{ __('client.business.location.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('client.business.location.sub') }}</p>

    <div class="bp-question">
        <span>{{ __('client.business.location.question') }}</span>
        <div class="mock-switch" role="group" aria-label="{{ __('client.business.location.question') }}">
            <button type="button" wire:click="$set('hasPremises', true)" @class(['is-active' => $hasPremises === true])>
                {{ __('client.business.location.yes') }}
            </button>
            <button type="button" wire:click="$set('hasPremises', false)" @class(['is-active' => $hasPremises === false])>
                {{ __('client.business.location.no') }}
            </button>
        </div>
    </div>

    @if ($hasPremises)
        <div class="bp-form">
            <x-catalog.form-row>
                <x-inputsform.input span="long" :label="__('client.business.location.address')" name="address"
                    :placeholder="__('client.business.location.address_placeholder')" wire:model="address" />
                <x-inputsform.input span="short" :label="__('client.business.location.city')" name="city"
                    :placeholder="__('client.business.location.city_placeholder')" wire:model="city" />
            </x-catalog.form-row>
            <x-catalog.form-row>
                <x-inputsform.input span="short" :label="__('client.business.location.country')" name="country"
                    :value="$this->countryName" disabled />
                <x-inputsform.input span="short" :label="__('client.business.location.province')" name="province"
                    :value="$this->provinceName" disabled />
            </x-catalog.form-row>
        </div>
    @endif

    <div class="bp-card-actions">
        <x-ui.button variant="primary" size="sm" wire:click="save">{{ __('client.business.actions.save') }}</x-ui.button>
    </div>
</x-ui.card>
