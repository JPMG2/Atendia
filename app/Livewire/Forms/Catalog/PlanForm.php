<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\UpdatePlan;
use App\Dto\PlanDto;
use App\Models\SubscriptionPlan;
use Illuminate\Validation\Rule;

/**
 * The plans master. Edit-only: a new plan needs its name and its card words,
 * which live in the language files, and a plan with businesses on it is never
 * deleted — so the wiring has no create action.
 */
class PlanForm extends BaseCatalogForm
{
    protected function catalog(): CatalogWiring
    {
        return new CatalogWiring(
            dto: PlanDto::class,
            model: SubscriptionPlan::class,
            create: null,
            update: UpdatePlan::class,
        );
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        $ladder = fn (string $field): array => [SubscriptionPlan::ladderRule($excludeId, $field)];

        return [
            'price' => ['required', 'integer', 'min:1', 'max:99999', ...$ladder('price')],
            'conversations_per_month' => ['required', 'integer', 'min:1', 'max:1000000', ...$ladder('conversations_per_month')],
            'team_seats' => ['required', 'integer', 'min:1', 'max:100', ...$ladder('team_seats')],
            'messages_per_hour' => ['required', 'integer', 'min:1', 'max:10000', ...$ladder('messages_per_hour')],
            'audio_minutes_per_month' => ['required', 'integer', 'min:0', 'max:100000', ...$ladder('audio_minutes_per_month')],
            'ask_per_month' => ['required', 'integer', 'min:0', 'max:100000', ...$ladder('ask_per_month')],
            'catalog_photos' => ['required', 'integer', 'min:0', 'max:1000000', ...$ladder('catalog_photos')],
            'photos_per_item' => ['required', 'integer', 'min:0', 'max:100', ...$ladder('photos_per_item')],
            'statistics' => ['required', Rule::in(['counts', 'patterns', 'trends']), ...$ladder('statistics')],
            'ai_alert_share' => ['required', 'integer', 'min:1', 'max:100'],
            'trial_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'reads_media' => ['boolean', ...$ladder('reads_media')],
            'departments' => ['boolean', ...$ladder('departments')],
            'daily_digest' => ['boolean', ...$ladder('daily_digest')],
            'is_featured' => ['boolean'],
        ];
    }

    protected function getValidationAttributes(): array
    {
        return collect(array_keys($this->getValidationRules()))
            ->mapWithKeys(fn (string $field): array => [$field => __('catalog.plan.fields.'.$field)])
            ->all();
    }
}
