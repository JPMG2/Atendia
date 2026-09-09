<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Events\BusinessConnectionSaved;
use App\Livewire\Forms\BaseForm;
use App\Models\Business;
use Illuminate\Support\Facades\Auth;

/**
 * Contact card of "Mi negocio": the public channels besides WhatsApp, both
 * optional. Writes through the connection slice, the same socket the wizard
 * uses, sending only its own fields.
 */
class ContactForm extends BaseForm
{
    public ?string $email = null;

    public ?string $web = null;

    /** Called from the component's `mount()`, not a Form hook. */
    public function setup(): void
    {
        $data = $this->client()->personalData()->data();

        $this->email = $data->email;
        $this->web = $data->web;
    }

    public function save(): NotificationDto
    {
        $validated = $this->validateServiceData();

        // Taken BEFORE the save: afterwards the model can no longer tell a
        // first address from a corrected one.
        $hadEmail = filled(Auth::user()->business?->email);

        // Leaves the closure by reference: the event fires OUTSIDE tryAction,
        // where a listener's failure cannot turn a good save into an error.
        $saved = null;

        $notification = $this->tryAction(function () use ($validated, &$saved): NotificationDto {

            $saved = $this->client()->personalData()->saveConnection($validated);

            if ($saved === null) {
                return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
            }

            return $this->notificationService()->notificationFor($saved, 'updated');

        }, __('notifications.not_updated'));

        if ($saved instanceof Business) {
            BusinessConnectionSaved::dispatch($saved, $hadEmail);
        }

        return $notification;
    }

    /** Rebuilt per request: a Livewire form cannot hold it in a constructor. */
    private function client(): Client
    {
        return Client::for(Auth::user());
    }

    protected function transformServiceData(): array
    {
        return [
            'email' => $this->email,
            'web' => $this->web,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'web' => ['nullable', 'url:http,https', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'email' => config('nicename.email'),
            'web' => config('nicename.web'),
        ];
    }
}
