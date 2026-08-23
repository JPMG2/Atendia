<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateServiceAttribute;
use App\Actions\Catalog\UpdateServiceAttribute;
use App\Dto\ServiceAttributeDto;
use App\Models\ServiceAttribute;
use App\Rules\AttributeValidator;

class ServiceAttributeForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: ServiceAttributeDto::class,
            model: ServiceAttribute::class,
            create: CreateServiceAttribute::class,
            update: UpdateServiceAttribute::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [

            // Único global: es la clave por la que el asistente y el pivot van a
            // referenciar el atributo. La columna es varchar(40).
            'code' => [
                ...AttributeValidator::uniqueIdNameLength('3', 'service_attributes', 'code', $excludeId),
                'max:40',
            ],

            'name' => AttributeValidator::uniqueIdNameLength('2', 'service_attributes', 'name', $excludeId),

            'description' => [
                'nullable',
                ...AttributeValidator::stringValid(false, '3'),
                'max:255',
            ],

            // El tipo de dato decide con qué control se pinta el campo: solo
            // valen los que config/attribute_types.php sabe dibujar. Y una vez
            // que un tipo de servicio ya usa el atributo, deja de ser editable:
            // cambiarlo rompería los valores ya cargados. Es la misma regla que
            // aplican Drupal y commercetools ("attribute level cannot be changed
            // after saving").
            'data_type' => [
                'required',
                'string',
                'in:'.implode(',', $this->editableDataTypes($excludeId)),
            ],

            // La columna es varchar(15) y la unidad es un símbolo corto ("min",
            // "personas"): sin este tope, el max:255 del helper la dejaba pasar y
            // reventaba en Postgres.
            'unit' => [
                'nullable',
                'string',
                'max:15',
            ],

            // Ya viene normalizado a lista por el DTO, o null si el tipo no es
            // lista. El tope evita una lista interminable en una columna jsonb.
            'options' => ['nullable', 'array', 'max:100'],
            'options.*' => ['string', 'max:60'],

            'is_multiple' => AttributeValidator::booleanValue(true),

            'sort_order' => [
                ...AttributeValidator::numericInteger(true, 0),
                'max:32767',
            ],

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    /**
     * Qué tipos de dato acepta la validación.
     *
     * Los de config, salvo que el atributo ya esté en uso: ahí el único válido
     * es el que ya tiene, así el intento de cambiarlo sale como error del campo
     * y no como valores rotos meses después.
     *
     * @return array<int, string>
     */
    private function editableDataTypes(?int $excludeId): array
    {
        $all = array_keys(ServiceAttribute::dataTypes());

        if ($excludeId === null) {
            return $all;
        }

        $attribute = ServiceAttribute::query()->find($excludeId);

        if ($attribute === null || ! $attribute->isInUse()) {
            return $all;
        }

        return [$attribute->data_type];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'code' => config('nicename.code'),
            'name' => config('nicename.name'),
            'description' => config('nicename.description'),
            'data_type' => config('nicename.data_type'),
            'unit' => config('nicename.unit'),
            'options' => config('nicename.options'),
            'is_multiple' => config('nicename.is_multiple'),
            'sort_order' => config('nicename.sort_order'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
