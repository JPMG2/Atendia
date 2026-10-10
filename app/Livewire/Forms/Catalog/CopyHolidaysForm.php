<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\CopyCountryHolidays;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use Illuminate\Validation\Rule;

/**
 * "Copy the holidays of one country to another": two countries and nothing
 * else. What would be copied is decided by the model, so the panel can show
 * it before anything is written.
 */
class CopyHolidaysForm extends BaseForm
{
    public string $source = '';

    public string $target = '';

    public function setup(): void
    {
        $this->reset();
    }

    public function save(): NotificationDto
    {
        $data = $this->validateServiceData();

        return $this->tryAction(function () use ($data): NotificationDto {
            $copied = app(CopyCountryHolidays::class)->handle($data['source'], $data['target']);

            if ($copied === 0) {
                return new NotificationDto(__('catalog.country_holiday.copy.nothing'), NotificationType::Info);
            }

            $this->setup();

            return new NotificationDto(
                trans_choice('catalog.country_holiday.copy.done', $copied, ['count' => $copied]),
                NotificationType::Success,
            );
        }, __('catalog.country_holiday.copy.failed'));
    }

    protected function transformServiceData(): array
    {
        return [
            'source' => $this->source === '' ? null : (int) $this->source,
            'target' => $this->target === '' ? null : (int) $this->target,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'source' => ['required', 'integer', Rule::exists('countries', 'id')],
            'target' => ['required', 'integer', Rule::exists('countries', 'id'), 'different:source'],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'source' => __('catalog.country_holiday.copy.source'),
            'target' => __('catalog.country_holiday.copy.target'),
        ];
    }
}
