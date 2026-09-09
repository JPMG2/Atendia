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

            'name' => AttributeValidator::uniqueIdNameLength('3', 'social_networks', 'name', $excludeId),

            'url' => [
                'required',
                'url:http,https',
                'max:255',
            ],

            'icon' => [
                'nullable',
                Rule::in(array_keys(config('icons'))),
            ],

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
