<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateSocialNetwork;
use App\Actions\Catalog\UpdateSocialNetwork;
use App\Classes\Main\Icons;
use App\Dto\SocialNetworkDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\SocialNetwork;
use App\Rules\AttributeValidator;
use Illuminate\Validation\Rule;

class SocialNetworkForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
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
        return self::fieldRules($excludeId);
    }

    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    public static function phoneFields(): array
    {
        return [
            ['key' => 'abbreviation', 'type' => 'text', 'label' => __('catalog.social_network.fields.abbreviation'), 'hint' => __('catalog.social_network.fields.abbreviation_hint'), 'max' => 10, 'mono' => true],
            ['key' => 'name', 'type' => 'text', 'label' => __('catalog.social_network.fields.name'), 'placeholder' => __('catalog.social_network.fields.name_placeholder'), 'required' => true],
            ['key' => 'url', 'type' => 'url', 'label' => __('catalog.social_network.fields.url'), 'hint' => __('catalog.social_network.fields.url_hint'), 'required' => true, 'max' => 255],
            ['key' => 'icon', 'type' => 'select', 'label' => __('catalog.social_network.fields.icon'), 'placeholder' => __('catalog.social_network.fields.icon_placeholder'), 'hint' => __('catalog.social_network.fields.icon_hint'), 'options' => Icons::options()],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.social_network.fields.status'), 'on_label' => __('catalog.social_network.status.active'), 'off_label' => __('catalog.social_network.status.inactive')],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
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

    public static function fieldNames(): array
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
