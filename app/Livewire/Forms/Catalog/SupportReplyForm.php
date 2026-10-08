<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CreateSupportReply;
use App\Actions\Catalog\UpdateSupportReply;
use App\Dto\SupportReplyDto;
use App\Models\SupportReply;
use App\Rules\AttributeValidator;

class SupportReplyForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
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
        return [
            'name' => AttributeValidator::uniqueIdNameLength('3', 'support_replies', 'name', $excludeId),

            'body' => ['required', 'string', 'min:10', 'max:'.SupportReply::BODY_MAX],

            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'name' => config('nicename.name'),
            'body' => config('nicename.body'),
            'is_active' => config('nicename.is_active'),
        ];
    }
}
