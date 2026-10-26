<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Classes\Main\Client;
use App\Classes\Main\Inventory;
use App\Dto\ProductDto;
use App\Livewire\Forms\Client\ProductForm;
use App\Models\ServiceType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A product of "Tus productos", rule for rule as the web's sheet validates it. A PUT is
 * partial: a box left out stays as stored, one sent empty clears it, and the values of the
 * type's own fields change only for the fields sent.
 */
class SaveProductRequest extends FormRequest
{
    private const array TEXT = ['name', 'code', 'description', 'price', 'stock'];

    /** @var list<int> */
    private array $listsForSingleAnswers = [];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $stored = $this->editing() ? $this->stored() : [];

        // Only a box that arrives as a scalar, or empty, is read; a list or an object is "nothing sent".
        // A text box holds text: a number typed there is the text it spells.
        // A boolean is no text either: it would reach the strictly typed DTO and crash it.
        $sent = array_filter($this->only([...self::TEXT, 'service_type_id']), fn (mixed $value): bool => $value === null || (is_scalar($value) && ! is_bool($value)));
        $sent = array_map(fn (mixed $value): mixed => is_int($value) || is_float($value) ? (string) $value : $value, array_intersect_key($sent, array_flip(self::TEXT))) + $sent;

        if ($this->has('in_stock')) {
            $sent['in_stock'] = $this->boolean('in_stock');
        }

        // The type decides which answers may be a list: only a multiple list is.
        $typeId = array_key_exists('service_type_id', $sent) ? $sent['service_type_id'] : ($stored['service_type_id'] ?? null);
        $set = collect(ServiceType::attributeSetFor($typeId === null || ! is_numeric($typeId) ? null : (int) $typeId));
        $multiple = $set->filter(fn (array $attribute): bool => $attribute['data_type'] === 'list' && $attribute['is_multiple'])->pluck('id')->all();
        $known = $set->pluck('id')->all();
        $values = [];

        foreach ((array) $this->input('attribute_values', []) as $id => $value) {
            if (is_array($value) && ! in_array((int) $id, $known, true)) {
                // The answer of a type no longer picked: its field is not on screen, so it cannot be blamed on the owner.
                continue;
            }

            if (is_array($value) && ! in_array((int) $id, $multiple, true)) {
                // A list where a single answer goes is no answer: refused in the field's name, never shaped into "no" or "nothing".
                $this->listsForSingleAnswers[] = (int) $id;
            } elseif (is_scalar($value) || $value === null || (is_array($value) && array_filter($value, 'is_scalar') === $value)) {
                $values[$id] = $value;
            }
        }

        $this->replace(ProductForm::payload(ProductDto::fromArray([...$stored, ...$sent, 'attribute_values' => $values + ($stored['attribute_values'] ?? [])])));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = ProductForm::fieldRules(
            $this->user()->business_id,
            ServiceType::attributeSetFor($this->input('service_type_id') === null ? null : (int) $this->input('service_type_id')),
            $this->editing() ? (int) $this->route('product') : null,
        );

        // A type the product already carries stays valid even if the catalog later switched it off:
        // it must not lock the product out of every edit. Only CHANGING the type is judged.
        if ($this->editing() && $this->input('service_type_id') === ($this->stored()['service_type_id'] ?? null)) {
            $rules['service_type_id'] = ['nullable', 'integer'];
        }

        return $rules;
    }

    /** The answers that came as a list where the field takes one value: refused in the field's own name. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $names = collect(ServiceType::attributeSetFor($this->input('service_type_id') === null ? null : (int) $this->input('service_type_id')))->pluck('label', 'id');

            foreach ($this->listsForSingleAnswers as $id) {
                $validator->errors()->add('attribute_values.'.$id, __('validation.in', ['attribute' => $names[$id] ?? (string) $id]));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ProductForm::fieldNames(ServiceType::attributeSetFor($this->input('service_type_id') === null ? null : (int) $this->input('service_type_id')));
    }

    /** The product as it is stored, in the sheet's own shape. */
    private function stored(): array
    {
        $product = $this->inventory()->products->firstWhere('id', (int) $this->route('product'));

        abort_if($product === null, 404);

        $dto = ProductDto::fromArray($product->toArray());
        // The decimal cast prints "185000.00"; the sheet reads it back as typed.
        $dto->price = $dto->price === null ? null : rtrim(rtrim($dto->price, '0'), '.');
        $dto->stock = $dto->stock === null ? null : rtrim(rtrim($dto->stock, '0'), '.');

        return $dto->toArray();
    }

    private function editing(): bool
    {
        return $this->route('product') !== null;
    }

    private function inventory(): Inventory
    {
        $inventory = Client::for($this->user())->inventory;

        // EnsureAppAccess already refused anyone without a business.
        abort_if($inventory === null, 404);

        return $inventory;
    }
}
