<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Classes\Main\Client;
use App\Dto\DtoCast;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Rules\AttributeValidator;
use Illuminate\Support\Facades\Auth;

/**
 * Location card of "Mi negocio". The GBP-style premises question folds the
 * address away on "no" — null means never answered, so neither button lights
 * up. Writes through the identity slice, sending only its own fields.
 */
class LocationForm extends BaseForm
{
    public ?bool $hasPremises = null;

    public ?string $address = null;

    public ?string $city = null;

    /** Called from the component's `mount()`, not a Form hook. */
    public function setup(): void
    {
        $data = $this->client()->personalData()->data();

        $this->hasPremises = $data->has_premises;
        $this->address = $data->address;
        $this->city = $data->city;
    }

    public function save(): NotificationDto
    {
        if (Auth::user()->business === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated): NotificationDto {

            // The screen speaks camelCase; the slice speaks columns.
            $business = $this->client()->personalData()->saveIdentity([
                'address' => $validated['address'],
                'city' => $validated['city'],
                'has_premises' => $validated['hasPremises'],
            ]);

            return $this->notificationService()->notificationFor($business, 'updated');

        }, __('notifications.not_updated'));
    }

    /** Rebuilt per request: a Livewire form cannot hold it in a constructor. */
    private function client(): Client
    {
        return Client::for(Auth::user());
    }

    protected function transformServiceData(): array
    {
        return [
            'hasPremises' => $this->hasPremises,
            'address' => DtoCast::squish($this->address),
            'city' => DtoCast::squish($this->city),
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'hasPremises' => ['nullable', ...AttributeValidator::booleanValue(false)],
            'address' => ['nullable', ...AttributeValidator::stringValid(false, '3')],
            'city' => ['nullable', ...AttributeValidator::stringValid(false, '3')],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'address' => config('nicename.address'),
            'city' => config('nicename.city'),
        ];
    }
}
