<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Classes\Main\Client;
use App\Classes\Main\ServiceMenu;
use App\Dto\ServiceDto;
use App\Livewire\Forms\Client\ServiceForm;
use App\Models\ServiceType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A service of "Tus servicios", rule for rule as the web's sheet validates it. A PUT is
 * partial: a box left out stays as stored, one sent empty clears it, and the values of
 * the type's own fields change only for the fields sent.
 */
class SaveServiceRequest extends FormRequest
{
    private const array TEXT = ['name', 'description', 'prep_note', 'price_type', 'price', 'deposit'];

    private const array NUMBERS = ['service_category_id', 'service_type_id', 'duration_minutes'];

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
        $sent = array_filter($this->only([...self::TEXT, ...self::NUMBERS]), fn (mixed $value): bool => $value === null || (is_scalar($value) && ! is_bool($value)));
        $sent = array_map(fn (mixed $value): mixed => is_int($value) || is_float($value) ? (string) $value : $value, array_intersect_key($sent, array_flip(self::TEXT))) + $sent;

        foreach (['is_active', 'is_featured'] as $switch) {
            if ($this->has($switch)) {
                $sent[$switch] = $this->boolean($switch);
            }
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
                // A list where a single answer goes is no answer: it is refused in the field's name (see `withValidator`),
                // never shaped into "no" or "nothing" by the normalizer.
                $this->listsForSingleAnswers[] = (int) $id;
            } elseif (is_scalar($value) || $value === null || (is_array($value) && array_filter($value, 'is_scalar') === $value)) {
                $values[$id] = $value;
            }
        }

        $dto = ServiceDto::fromArray([...$stored, ...$sent, 'attribute_values' => $values + ($stored['attribute_values'] ?? [])]);

        $this->replace(ServiceForm::payload($dto));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = ServiceForm::fieldRules(
            $this->user()->business_id,
            ServiceType::attributeSetFor($this->input('service_type_id') === null ? null : (int) $this->input('service_type_id')),
            $this->editing() ? (int) $this->route('service') : null,
            array_keys($this->menu()->typeOptions),
        );

        // A type the service already carries stays valid even if the catalog later switched it off, or a
        // suggestion gave it one the sheet does not offer: it must not lock the service out of every edit.
        // Only CHANGING the type is judged.
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
        return ServiceForm::fieldNames(ServiceType::attributeSetFor($this->input('service_type_id') === null ? null : (int) $this->input('service_type_id')));
    }

    /** The service as it is stored, in the sheet's own shape. */
    private function stored(): array
    {
        $service = $this->menu()->services->firstWhere('id', (int) $this->route('service'));

        abort_if($service === null, 404);

        $dto = ServiceDto::fromArray($service->toArray());
        // The decimal cast prints "12000.00"; the sheet reads it back as typed.
        $dto->price = $dto->price === null ? null : rtrim(rtrim($dto->price, '0'), '.');
        $dto->deposit = $dto->deposit === null ? null : rtrim(rtrim($dto->deposit, '0'), '.');

        return $dto->toArray();
    }

    private function editing(): bool
    {
        return $this->route('service') !== null;
    }

    private function menu(): ServiceMenu
    {
        $menu = Client::for($this->user())->serviceMenu;

        // EnsureAppAccess already refused anyone without a business.
        abort_if($menu === null, 404);

        return $menu;
    }
}
