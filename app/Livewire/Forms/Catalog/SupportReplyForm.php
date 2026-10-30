<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateSupportReply;
use App\Actions\Catalog\UpdateSupportReply;
use App\Dto\SupportReplyDto;
use App\Interfaces\Catalog\EditableFromPhone;
use App\Models\SupportReply;
use App\Rules\AttributeValidator;

class SupportReplyForm extends BaseCatalogForm implements EditableFromPhone
{
    protected function catalog(): CatalogWiring
    {
        return self::wiring();
    }

    public static function wiring(): CatalogWiring
    {
        return new CatalogWiring(
            dto: SupportReplyDto::class,
            model: SupportReply::class,
            create: CreateSupportReply::class,
            update: UpdateSupportReply::class,
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
            ['key' => 'name', 'type' => 'text', 'label' => __('catalog.support_reply.fields.name'), 'placeholder' => __('catalog.support_reply.fields.name_placeholder'), 'required' => true, 'max' => 255],
            ['key' => 'is_active', 'type' => 'switch', 'label' => __('catalog.support_reply.fields.status'), 'on_label' => __('catalog.support_reply.status.active'), 'off_label' => __('catalog.support_reply.status.inactive')],
            ['key' => 'body', 'type' => 'textarea', 'label' => __('catalog.support_reply.fields.body'), 'hint' => __('catalog.support_reply.fields.body_hint'), 'required' => true, 'max' => SupportReply::BODY_MAX],
        ];
    }

    public static function fieldRules(?int $excludeId = null, array $data = []): array
    {
        return [
            'name' => AttributeValidator::uniqueIdNameLength('3', 'support_replies', 'name', $excludeId),

            'body' => ['required', 'string', 'min:10', 'max:'.SupportReply::BODY_MAX],

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    public static function fieldNames(): array
    {
        return [
            'name' => config('nicename.name'),
            'body' => config('nicename.body'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
