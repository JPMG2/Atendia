<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateCurrency;
use App\Actions\Catalog\UpdateCurrency;
use App\Dto\CurrencyDto;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\Currency;
use App\Rules\AttributeValidator;
use Livewire\Attributes\Locked;

class CurrencyForm extends BaseForm
{
    #[Locked]
    public ?int $currencyId = null;

    public ?CurrencyDto $currencyData = null;

    public function setup(): void
    {
        $this->currencyData = new CurrencyDto;
    }

    public function storeCurrency(): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated) {

            $model = app(CreateCurrency::class)->handle($validated);

            return $this->notificationService()->notificationFor($model, 'created');

        }, __('notifications.not_created'));
    }

    public function updateCurrency(): NotificationDto
    {
        if ($this->currencyId === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData($this->currencyId);

        return $this->tryAction(function () use ($validated) {

            $model = app(UpdateCurrency::class)->handle($this->currencyId, $validated);

            return $this->notificationService()->notificationFor($model, 'updated');

        }, __('notifications.not_updated'));
    }

    public function loadCurrencyData(int $id): bool
    {
        $data = $this->findCurrencyData($id);

        if ($data === null) {
            return false;
        }

        $this->currencyId = $id;
        $this->currencyData = CurrencyDto::fromArray($data->toArray());

        return true;
    }

    public function findCurrencyData(int $id): ?Currency
    {
        return Currency::query()->find($id);
    }

    protected function transformServiceData(): array
    {
        return $this->currencyData->toPayload();
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [

            'code' => [
                ...AttributeValidator::uniqueAlpha(true, '3', false, 'currencies', 'code', $excludeId),
                'size:3',
            ],

            'name' => AttributeValidator::stringValid(true, '3'),

            // La columna es varchar(10) y el input pone maxlength=5: sin este tope
            // el max:255 que trae stringValid dejaba pasar un símbolo que después
            // reventaba en Postgres. 5 es lo que ya pide la UI ("$, US$, €").
            'symbol' => [
                ...AttributeValidator::stringValid(true, '1'),
                'max:5',
            ],

            'decimal_places' => [
                ...AttributeValidator::numericInteger(true, 0),
                'max:2',
            ],
            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'code' => config('nicename.code'),
            'name' => config('nicename.name'),
            'symbol' => config('nicename.symbol'),
            'decimal_places' => config('nicename.decimal_places'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
