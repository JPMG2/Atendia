<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateCountry;
use App\Actions\Catalog\UpdateCountry;
use App\Dto\CountryDto;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Models\Country;
use App\Rules\AttributeValidator;
use Livewire\Attributes\Locked;

class CountryForm extends BaseCatalogForm
{
    #[Locked]
    public ?int $countryId = null;

    public ?CountryDto $countryData = null;

    public function setup(): void
    {
        $this->countryData = new CountryDto;
    }

    public function store(): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated) {

            $model = app(CreateCountry::class)->handle($validated);

            return $this->notificationService()->notificationFor($model, 'created');

        }, __('notifications.not_created'));
    }

    public function update(): NotificationDto
    {
        if ($this->countryId === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData($this->countryId);

        return $this->tryAction(function () use ($validated) {

            $model = app(UpdateCountry::class)->handle($this->countryId, $validated);

            return $this->notificationService()->notificationFor($model, 'updated');

        }, __('notifications.not_updated'));
    }

    public function loadData(int $id): bool
    {
        $data = $this->findCountryData($id);

        if ($data === null) {
            return false;
        }

        $this->countryId = $id;
        $this->countryData = CountryDto::fromArray($data->toArray());

        return true;
    }

    public function findCountryData(int $id): ?Country
    {
        return Country::query()->find($id);
    }

    protected function transformServiceData(): array
    {
        return $this->countryData->toPayload();
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [

            // La FK es obligatoria en la tabla (`constrained()`), así que sin
            // `required` un alta sin moneda reventaría en Postgres en vez de
            // marcar el campo.
            'currency_id' => AttributeValidator::requireAndExists('currencies', 'id', 'currency_id', true),

            // `name` es UNIQUE en la tabla: sin la regla, un país repetido no
            // sería un error de campo sino un crash de base atrapado por
            // tryAction, es decir un toast vago en vez de un mensaje útil.
            'name' => AttributeValidator::uniqueIdNameLength('3', 'countries', 'name', $excludeId),

            'code' => [
                ...AttributeValidator::uniqueAlpha(true, '3', false, 'countries', 'code', $excludeId),
                'size:3',
            ],

            // Opcional (la columna es nullable) y acotado a lo que pide la UI
            // (maxlength=6): sin el `nullable` un país sin código telefónico
            // rebotaría contra el `min:1` que trae digitValid().
            'phone_code' => [
                'nullable',
                ...AttributeValidator::digitValid('1', false),
                'max:6',
            ],

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'currency_id' => config('nicename.currency_id'),
            'name' => config('nicename.name'),
            'code' => config('nicename.code'),
            'phone_code' => config('nicename.phone_code'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
