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

            // `name` is UNIQUE on the table: without the rule a repeated network is
            // a database crash caught by tryAction — a vague toast instead of a
            // useful message.
            'name' => AttributeValidator::uniqueIdNameLength('3', 'social_networks', 'name', $excludeId),

            // webValid() is NOT used: it adds `active_url`, which resolves DNS on
            // every save. A master that only stores the base URL cannot depend on
            // the network being online — nor can the tests.
            'url' => [
                'required',
                'url:http,https',
                'max:255',
            ],

            // The icon is the KEY in config/icons.php, not free text: a missing one
            // draws nothing and the row goes mute. It is validated against the real
            // glyph catalog, the only source of truth.
            'icon' => [
                'nullable',
                Rule::in(array_keys(config('icons'))),
            ],

            // Optional and capped at what the column and the UI take: without
            // `nullable` a network with no short form would bounce off the `min:1`
            // in stringValid().
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
