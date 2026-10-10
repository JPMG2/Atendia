<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Actions\Catalog\MarkBridgeRange;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Validation\Rule;

/**
 * A drag over the year view: which country and which first and last day. Both
 * ends are read strictly, like a single click, and the span is capped.
 */
class BridgeRangeForm extends BaseForm
{
    public string $country = '';

    public string $from = '';

    public string $to = '';

    /** @var list<string> The dates the last save marked, for the caller to offer the notice for. */
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
            $this->marked = app(MarkBridgeRange::class)->handle($data['country'], $data['from'], $data['to']);

            if ($this->marked === []) {
                return new NotificationDto(__('catalog.country_holiday.bridge.range_nothing'), NotificationType::Info);
            }

            return new NotificationDto(
                trans_choice('catalog.country_holiday.bridge.range_done', count($this->marked), ['count' => count($this->marked)]),
                NotificationType::Success,
            );
        }, __('catalog.country_holiday.bridge.failed'));
    }

    protected function transformServiceData(): array
    {
        return [
            'country' => $this->country === '' ? null : (int) $this->country,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        $strict = fn (string $attribute, mixed $value, Closure $fail): mixed => CarbonImmutable::createFromFormat('!Y-m-d', (string) $value)?->format('Y-m-d') === $value
            ? null
            : $fail(__('catalog.country_holiday.errors.no_such_day'));

        return [
            'country' => ['required', 'integer', Rule::exists('countries', 'id')],
            'from' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31', $strict],
            'to' => [
                'bail',
                'required',
                'date_format:Y-m-d',
                'after_or_equal:from',
                'before_or_equal:2100-12-31',
                $strict,
                fn (string $attribute, mixed $value, Closure $fail): mixed => rescue(fn (): int => (int) CarbonImmutable::parse($this->from)->diffInDays(CarbonImmutable::parse((string) $value)), MarkBridgeRange::MAX_DAYS, false) < MarkBridgeRange::MAX_DAYS
                    ? null
                    : $fail(__('catalog.country_holiday.errors.range_too_long', ['days' => MarkBridgeRange::MAX_DAYS])),
            ],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'country' => __('catalog.country_holiday.fields.country_id'),
            'from' => __('catalog.country_holiday.fields.on_date'),
            'to' => __('catalog.country_holiday.fields.on_date'),
        ];
    }
}
