<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Actions\Billing\ConfirmExchangeRate;
use App\Actions\Billing\RecordManualExchangeRate;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\ExchangeRate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * The rate she types when no source answers, and her yes to a jump bigger
 * than she allows. Both move what every bolivar payment is charged with, so
 * both ask for their own key and not only for the door of the screen.
 */
class ExchangeRateForm extends BaseForm
{
    public const string PERMISSION = 'payments.rate';

    public string $rate = '';

    public string $rate_date = '';

    public function setup(): void
    {
        $charging = ExchangeRate::charging();

        $this->rate = $charging === null ? '' : ExchangeRate::formatRate($charging->official);
        $this->rate_date = ExchangeRate::today()->toDateString();
    }

    /**
     * When the typed value moves more than she allows from the confirmed rate
     * before its value date: the jump and the number as it was read, so she
     * sees "1,05" if a dot was taken wrong. Null inside the range or unreadable.
     *
     * @return array{percent: string, rate: string}|null
     */
    public function jumpBeyondRange(): ?array
    {
        $typed = $this->typedRate();
        $date = $this->typedDate();
        $reference = $date === null ? null : ExchangeRate::previousOfficial($date->addDay());

        if ($typed === null || $reference === null || ExchangeRate::isWithinJump($typed, $reference)) {
            return null;
        }

        return [
            'percent' => number_format(ExchangeRate::jumpPercent($typed, $reference), 1, ',', '.'),
            'rate' => ExchangeRate::formatRate($typed),
        ];
    }

    public function save(): NotificationDto
    {
        Gate::authorize(self::PERMISSION);
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated): NotificationDto {
            $date = CarbonImmutable::parse($validated['rate_date'], ExchangeRate::TIMEZONE)->startOfDay();
            $saved = app(RecordManualExchangeRate::class)->handle(Auth::user(), $date, (float) $validated['rate']);

            // Say what really happens: a future date waits, an old one is overtaken by a newer value date.
            $message = match (true) {
                $date->greaterThan(ExchangeRate::today()) => __('exchange_rate.manual.saved_future', ['date' => $date->format('d/m/Y')]),
                ExchangeRate::charging()?->is($saved) !== true => __('exchange_rate.manual.saved_past', ['date' => $date->format('d/m/Y')]),
                default => __('exchange_rate.manual.saved'),
            };

            return new NotificationDto($message, NotificationType::Success);
        }, __('notifications.not_updated'));
    }

    public function approveWaiting(int $rateId, string $shown): NotificationDto
    {
        Gate::authorize(self::PERMISSION);
        $waiting = ExchangeRate::awaitingConfirmation();

        if ($waiting === null || $waiting->getKey() !== $rateId) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        if (ExchangeRate::formatRate($waiting->official) !== $shown) {
            return new NotificationDto(__('exchange_rate.waiting.changed'), NotificationType::Error);
        }

        return $this->tryAction(function () use ($waiting): NotificationDto {
            app(ConfirmExchangeRate::class)->handle(Auth::user(), $waiting);

            return new NotificationDto(__('exchange_rate.waiting.confirmed'), NotificationType::Success);
        }, __('notifications.not_updated'));
    }

    /**
     * As people type it in Venezuela: the comma is the decimal mark and the dot
     * groups thousands, so "1.050" is one thousand fifty and "889,5754" has
     * decimals. A lone dot that does not group thousands ("889.5754") is decimal.
     */
    private function typedRate(): ?float
    {
        $raw = str_replace(' ', '', trim($this->rate));

        if ($raw === '') {
            return null;
        }

        $normalized = match (true) {
            str_contains($raw, ',') => str_replace(['.', ','], ['', '.'], $raw),
            preg_match('/^\d{1,3}(\.\d{3})+$/', $raw) === 1 => str_replace('.', '', $raw),
            default => $raw,
        };

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function typedDate(): ?CarbonImmutable
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->rate_date) === 1
            ? CarbonImmutable::parse($this->rate_date, ExchangeRate::TIMEZONE)->startOfDay()
            : null;
    }

    protected function transformServiceData(): array
    {
        return ['rate' => $this->typedRate() ?? $this->rate, 'rate_date' => $this->rate_date];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'rate' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            // The BCV publishes the next business day's rate ahead of time, so a few days forward are allowed.
            'rate_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.ExchangeRate::today()->addDays(5)->toDateString()],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'rate' => __('exchange_rate.manual.rate'),
            'rate_date' => __('exchange_rate.manual.date'),
        ];
    }
}
