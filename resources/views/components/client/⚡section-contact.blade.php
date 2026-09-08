<?php

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Services\NotificationService;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Contact card of "Mi negocio": the public channels besides WhatsApp, both
 * optional. Writes through the connection slice, the same socket the wizard
 * uses, sending only its own fields.
 */
new class extends Component
{
    use HasNotifications;

    public ?string $email = null;

    public ?string $web = null;

    public function mount(): void
    {
        $data = $this->client()->personalData()->data();

        $this->email = $data->email;
        $this->web = $data->web;
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
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'web' => ['nullable', 'url:http,https', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'email' => config('nicename.email'),
            'web' => config('nicename.web'),
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $business = $this->client()->personalData()->saveConnection($validated);

        if ($business === null) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return;
        }

        $this->dispatchNotification(resolve(NotificationService::class)->notificationFor($business, 'updated'));
    }
};
?>

<x-ui.card class="bp-card" data-section="contacto">
    <div class="bp-card-head">
        <h2>{{ __('client.business.contact.title') }}</h2>
    </div>
    <p class="bp-card-sub">{{ __('client.business.contact.sub') }}</p>

    <x-catalog.form-row>
        <x-inputsform.input span="text" :label="__('client.business.contact.email').' · '.__('client.business.optional')"
            name="email" wire:model="email" />
        <x-inputsform.input span="text" :label="__('client.business.contact.web').' · '.__('client.business.optional')"
            name="web" :placeholder="__('client.business.contact.web_placeholder')" wire:model="web" />
    </x-catalog.form-row>

    <div class="bp-card-actions">
        <x-ui.button variant="primary" size="sm" wire:click="save">{{ __('client.business.actions.save') }}</x-ui.button>
    </div>
</x-ui.card>
