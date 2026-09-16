<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Dto\ProductDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\ServiceModality;
use App\Models\ServiceType;
use App\Rules\AttributeValidator;
use App\Rules\AttributeValueRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The product sheet of "Tus productos": one full row, created or edited in
 * place. Reads and saves through the client's main class — the same door
 * every profile card uses.
 */
class ProductForm extends BaseForm
{
    public ProductDto $data;

    public ?int $editingId = null;

    /** Called from the component's `mount()` and on every sheet open — not a Form hook. */
    public function setup(?int $productId = null): void
    {
        $this->editingId = $productId;
        $this->resetErrorBag();

        $product = $productId === null
            ? null
            : $this->client()->inventory?->products->firstWhere('id', $productId);

        $this->data = $product === null
            ? new ProductDto
            : ProductDto::fromArray($product->toArray());

        // The decimal cast prints "185000.00"; the client typed "185000" and
        // should find it back that way.
        $this->data->price = $this->trimDecimal($this->data->price);
        $this->data->stock = $this->trimDecimal($this->data->stock);
    }

    public function save(): NotificationDto
    {
        $inventory = $this->client()->inventory;

        if ($inventory === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData($this->editingId);

        // The empty keys validation needed are noise on the stored row.
        $validated['attribute_values'] = AttributeValueRules::strip($validated['attribute_values'] ?? []);

        return $this->tryAction(function () use ($inventory, $validated): NotificationDto {

            $product = $inventory->save($validated, $this->editingId);

            return $this->notificationService()->notificationFor($product, $this->editingId === null ? 'created' : 'updated');

        }, __('notifications.not_updated'));
    }

    /**
     * Validates the products-import spreadsheet before the reader opens it.
     * The upload itself lives on the COMPONENT (`wire:model="upload"`), so
     * the file arrives as an argument; the error still lands on `upload`.
     */
    public function validateImportUpload(mixed $file): void
    {
        Validator::make(
            ['upload' => $file],
            ['upload' => AttributeValidator::spreadsheetUpload()],
            [],
            ['upload' => __('wizard.fields.import_file')],
        )->validate();
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
                Rule::unique('products', 'name')
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at')
                    ->ignore($excludeId),
            ],
            // Trashed rows count: a code is a sync identity (future
            // retailer_id) and must never revive duplicated.
            'code' => [
                'nullable',
                ...AttributeValidator::stringValid(false, '1'),
                'max:100',
                Rule::unique('products', 'code')
                    ->where('business_id', $businessId)
                    ->ignore($excludeId),
            ],
            // Only producto-modality types belong on this screen.
            'service_type_id' => ['nullable', 'integer', Rule::exists('service_types', 'id')->where('is_active', true)->where('service_modality_id', ServiceModality::idFromCode('producto'))],
            'description' => ['nullable', ...AttributeValidator::longTextValid(9999)],
            'price' => [...AttributeValidator::numericDecimal(false), 'max:9999999999'],
            'stock' => [...AttributeValidator::numericDecimal(false), 'max:9999999999'],
            'in_stock' => ['boolean'],
            'is_active' => ['boolean'],
            ...AttributeValueRules::rules($this->attributeSet()),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationAttributes(): array
    {
        return [
            'name' => __('client.products.field_name'),
            'service_type_id' => __('client.products.field_type'),
            'code' => __('client.products.field_code'),
            'description' => __('client.products.field_description'),
            'price' => __('client.products.field_price'),
            'stock' => __('client.products.field_stock'),
            'in_stock' => __('client.products.sheet_available'),
            ...AttributeValueRules::attributes($this->attributeSet()),
        ];
    }
}
