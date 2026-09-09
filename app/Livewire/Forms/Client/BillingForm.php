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
use Illuminate\Validation\Rule;

/**
 * Currency and billing card of "Mi negocio". Everything optional: no tax
 * data means the invoice goes out to a natural person, and the reference
 * currency mirrors the Venezuelan "Ref" habit. Reads and saves through the
 * client's main class — the same door the wizard uses.
 */
class BillingForm extends BaseForm
{
    public ?int $currency_id = null;

    public ?int $reference_currency_id = null;

    public ?int $tax_condition_id = null;

    public ?string $tax_id = null;

    /** Called from the component's `mount()`, not a Form hook. */
    public function setup(): void
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

    public function save(): NotificationDto
    {
        $details = $this->client()->taxDetails();

        if ($details === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($details, $validated): NotificationDto {

            $business = $details->save($validated);

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
            'currency_id' => $this->currency_id,
            'reference_currency_id' => $this->reference_currency_id,
            'tax_condition_id' => $this->tax_condition_id,
            'tax_id' => DtoCast::squish($this->tax_id),
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'reference_currency_id' => ['nullable', 'integer', 'different:currency_id', Rule::exists('currencies', 'id')->where('is_active', true)],
            'tax_condition_id' => ['nullable', 'integer', Rule::exists('tax_conditions', 'id')->where('country_id', Auth::user()?->business?->country_id)],
            'tax_id' => ['nullable', ...AttributeValidator::stringValid(false, '1'), 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'currency_id' => __('client.business.billing.currency'),
            'reference_currency_id' => __('client.business.billing.reference'),
            'tax_condition_id' => __('client.business.billing.tax_condition'),
            'tax_id' => __('client.business.billing.tax_id'),
        ];
    }
}
