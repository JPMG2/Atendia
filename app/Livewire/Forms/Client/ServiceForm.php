<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Dto\ServiceDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\Service;
use App\Models\ServiceType;
use App\Rules\AttributeValidator;
use App\Rules\AttributeValueRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * The service sheet of "Tus servicios": one full row, created or edited in
 * place. Reads and saves through the client's main class — the same door
 * every profile card uses.
 */
class ServiceForm extends BaseForm
{
    public ServiceDto $data;

    public ?int $editingId = null;

    /**
     * Called from the component's `mount()` and on every sheet open — not a
     * Form hook. A suggestion chip lands here as a prefilled blank sheet.
     */
    public function setup(?int $serviceId = null, string $name = ''): void
    {
        $this->editingId = $serviceId;
        $this->resetErrorBag();

        $service = $serviceId === null
            ? null
            : $this->client()->serviceMenu?->services->firstWhere('id', $serviceId);

        $this->data = $service === null
            ? new ServiceDto(name: $name)
            : ServiceDto::fromArray($service->toArray());

        // The decimal cast prints "12000.00"; the client typed "12000" and
        // should find it back that way.
        $this->data->price = $this->trimDecimal($this->data->price);
        $this->data->deposit = $this->trimDecimal($this->data->deposit);
    }

    public function save(): NotificationDto
    {
        $menu = $this->client()->serviceMenu;

        if ($menu === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData($this->editingId);

        // The empty keys validation needed are noise on the stored row.
        $validated['attribute_values'] = AttributeValueRules::strip($validated['attribute_values'] ?? []);

        return $this->tryAction(function () use ($menu, $validated): NotificationDto {

            $service = $menu->save($validated, $this->editingId);

            return $this->notificationService()->notificationFor($service, $this->editingId === null ? 'created' : 'updated');

        }, __('notifications.not_updated'));
    }

    /** Rebuilt per request: a Livewire form cannot hold it in a constructor. */
    private function client(): Client
    {
        return Client::for(Auth::user());
    }

    private function trimDecimal(?string $value): ?string
    {
        return $value === null ? null : rtrim(rtrim($value, '0'), '.');
    }

    protected function transformServiceData(): array
    {
        $payload = $this->data->toPayload();
        $payload['attribute_values'] = AttributeValueRules::normalize($this->attributeSet(), $this->data->attribute_values);

        return $payload;
    }

    /** The picked type's attribute set — what the dynamic rules hang on. */
    private function attributeSet(): array
    {
        return ServiceType::attributeSetFor($this->data->service_type_id);
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        $businessId = Auth::user()?->business_id;

        return [
            'name' => [
                ...AttributeValidator::stringValid(true, '2'),
                Rule::unique('services', 'name')
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at')
                    ->ignore($excludeId),
            ],
            'service_category_id' => ['nullable', 'integer', Rule::exists('service_categories', 'id')->where('business_id', $businessId)],
            'service_type_id' => ['nullable', 'integer', Rule::exists('service_types', 'id')->where('is_active', true)],
            'price_type' => ['required', Rule::in(Service::PRICE_TYPES)],
            'price' => [...AttributeValidator::numericDecimal(false), 'max:9999999999'],
            'deposit' => [...AttributeValidator::numericDecimal(false), 'max:9999999999'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'description' => ['nullable', ...AttributeValidator::stringValid(false, '2')],
            'prep_note' => ['nullable', ...AttributeValidator::stringValid(false, '2')],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            ...AttributeValueRules::rules($this->attributeSet()),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'name' => __('client.services.field_name'),
            'service_category_id' => __('client.services.field_category'),
            'service_type_id' => __('client.services.field_type'),
            'price_type' => __('client.services.field_price_type'),
            'price' => __('client.services.field_amount'),
            'deposit' => __('client.services.field_deposit'),
            'duration_minutes' => __('client.services.field_minutes'),
            'description' => __('client.services.field_description'),
            'prep_note' => __('client.services.field_prep'),
            'is_active' => __('client.services.offered'),
            'is_featured' => __('client.services.featured'),
            ...AttributeValueRules::attributes($this->attributeSet()),
        ];
    }
}
