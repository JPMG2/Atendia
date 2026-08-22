<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateSocialNetwork;
use App\Actions\Catalog\UpdateSocialNetwork;
use App\Dto\SocialNetworkDto;
use App\Models\SocialNetwork;
use App\Rules\AttributeValidator;
use Illuminate\Validation\Rule;

class SocialNetworkForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: SocialNetworkDto::class,
            model: SocialNetwork::class,
            create: CreateSocialNetwork::class,
            update: UpdateSocialNetwork::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [

            // `name` es UNIQUE en la tabla: sin la regla, una red repetida no sería
            // un error de campo sino un crash de base atrapado por tryAction, es
            // decir un toast vago en vez de un mensaje útil.
            'name' => AttributeValidator::uniqueIdNameLength('3', 'social_networks', 'name', $excludeId),

            // Obligatoria y acotada a la columna (string 255). NO se usa webValid():
            // ese helper suma `active_url`, que resuelve DNS en cada guardado — un
            // maestro que solo carga la URL base no puede depender de que la red
            // esté online (ni los tests de una red externa).
            'url' => [
                'required',
                'url:http,https',
                'max:255',
            ],

            // El ícono es la CLAVE de config/icons.php, no un texto libre: si no
            // existe, <x-icon> no dibuja nada y la fila queda muda. Se valida
            // contra el catálogo real de glifos, que es la única fuente de verdad.
            'icon' => [
                'nullable',
                Rule::in(array_keys(config('icons'))),
            ],

            // Opcional (la columna es nullable) y acotada a lo que aguanta la
            // columna y pide la UI (string 10 / maxlength=10): sin el `nullable`
            // una red sin abreviatura rebotaría contra el `min:1` de stringValid().
            'abbreviation' => [
                'nullable',
                ...AttributeValidator::stringValid(false, '1'),
                'max:10',
            ],

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'name' => config('nicename.name'),
            'url' => config('nicename.url'),
            'icon' => config('nicename.icon'),
            'abbreviation' => config('nicename.abbreviation'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
