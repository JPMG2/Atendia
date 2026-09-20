<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Dto\DtoCast;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\Customer;
use App\Rules\AttributeValidator;
use Livewire\Attributes\Locked;

/**
 * The owner-editable side of a customer record. Only human fields travel:
 * phone, counters and AI provenance never pass through this form.
 */
class CustomerForm extends BaseForm
{
    #[Locked]
    public ?int $customerId = null;

    public string $name = '';

    public string $email = '';

    /** ISO from the datepicker ("Y-m-d"), empty when unknown. */
    public string $birthday = '';

    public string $notes = '';

    public function setup(Customer $customer): void
    {
        $this->customerId = $customer->id;
        $this->name = (string) $customer->name;
        $this->email = (string) $customer->email;
        $this->birthday = (string) $customer->birthday?->toDateString();
        $this->notes = (string) $customer->notes;

        $this->resetErrorBag();
    }

    public function save(): NotificationDto
    {
        $customer = Customer::query()->find($this->customerId);

        if ($customer === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($customer, $validated): NotificationDto {

            $customer->update($validated);

            return $this->notificationService()->notificationFor($customer, 'updated');

        }, __('notifications.not_updated'));
    }

    protected function transformServiceData(): array
    {
        return [
            'name' => DtoCast::squish($this->name),
            'email' => DtoCast::squish($this->email),
            'birthday' => trim($this->birthday) === '' ? null : trim($this->birthday),
            'notes' => trim($this->notes) === '' ? null : trim($this->notes),
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'name' => AttributeValidator::stringValid(false, '2'),
            'email' => ['nullable', 'email', 'max:150'],
            'birthday' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'name' => __('client.customers.field_name'),
            'email' => __('client.customers.field_email'),
            'birthday' => __('client.customers.field_birthday'),
            'notes' => __('client.customers.field_notes'),
        ];
    }
}
