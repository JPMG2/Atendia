<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\ToggleBridgeHoliday;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Validation\Rule;

/**
 * A click on a day of the year view: which country and which date. The date
 * is read strictly — a day that does not exist must be refused, not slid to
 * the next month the way a lenient parser would.
 */
class BridgeHolidayForm extends BaseForm
{
    public string $country = '';

    public string $date = '';

    /** @var list<string> The day the last save turned INTO a bridge (empty when it was switched off). */
    public array $marked = [];

    public function setup(): void
    {
        $this->reset();
    }

    public function save(): NotificationDto
    {
        $this->marked = [];
        $data = $this->validateServiceData();

        return $this->tryAction(function () use ($data): NotificationDto {
            $state = app(ToggleBridgeHoliday::class)->handle($data['country'], $data['date']);
            $this->marked = $state === ToggleBridgeHoliday::SWITCHED_OFF ? [] : [$data['date']];
            $when = CarbonImmutable::parse($data['date'])->format('d/m/Y');

            return new NotificationDto(
                __('catalog.country_holiday.bridge.'.$state, ['date' => $when]),
                NotificationType::Success,
            );
        }, __('catalog.country_holiday.bridge.failed'));
    }

    protected function transformServiceData(): array
    {
        return [
            'country' => $this->country === '' ? null : (int) $this->country,
            'date' => $this->date,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'country' => ['required', 'integer', Rule::exists('countries', 'id')],
            'date' => [
                'bail',
                'required',
                'date_format:Y-m-d',
                'after_or_equal:2000-01-01',
                'before_or_equal:2100-12-31',
                fn (string $attribute, mixed $value, Closure $fail): mixed => CarbonImmutable::createFromFormat('!Y-m-d', (string) $value)?->format('Y-m-d') === $value
                    ? null
                    : $fail(__('catalog.country_holiday.errors.no_such_day')),
            ],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'country' => __('catalog.country_holiday.fields.country_id'),
            'date' => __('catalog.country_holiday.fields.on_date'),
        ];
    }
}
