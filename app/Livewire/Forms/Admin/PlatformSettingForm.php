<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\PlatformSetting;

/**
 * The behaviour knobs, edited together and saved once.
 *
 * Each row carries its own bounds, so the rules are built from the TABLE and
 * not written here: a threshold whose floor lives in two places drifts, and a
 * zero in the wrong one would read a live conversation as finished.
 */
class PlatformSettingForm extends BaseForm
{
    /**
     * The value on screen, keyed by the setting's ID and never by its key: a
     * key carries dots, and both Livewire and the validator read a dot as one
     * level deeper, so `value.analysis.idle_hours` would address a nest that
     * does not exist and the error would never reach its field.
     *
     * @var array<int, string>
     */
    public array $value = [];

    public function setup(): void
    {
        $this->value = PlatformSetting::board()
            ->mapWithKeys(fn (PlatformSetting $setting): array => [$setting->id => (string) $setting->value])
            ->all();
    }

    /** Puts every knob back where the code left it, without saving. */
    public function restoreDefaults(): void
    {
        $this->value = PlatformSetting::board()
            ->mapWithKeys(fn (PlatformSetting $setting): array => [$setting->id => (string) $setting->default_value])
            ->all();
    }

    /**
     * Only the knobs she moved. Rewriting all of them would make every audit
     * entry a claim about something she never touched.
     */
    public function save(): NotificationDto
    {
        $rows = PlatformSetting::board();
        $changed = $rows->filter(fn (PlatformSetting $s): bool => (string) ($this->value[$s->id] ?? '') !== (string) $s->value);

        if ($changed->isEmpty()) {
            return new NotificationDto(__('admin.settings.nothing_changed'), NotificationType::Info);
        }

        $this->validateServiceData();

        return $this->tryAction(function () use ($changed): NotificationDto {
            foreach ($changed as $setting) {
                $setting->update(['value' => trim((string) $this->value[$setting->id])]);
            }

            $this->setup();

            return new NotificationDto(
                trans_choice('admin.settings.saved', $changed->count(), ['count' => $changed->count()]),
                NotificationType::Success,
            );
        }, __('notifications.not_updated'));
    }

    protected function transformServiceData(): array
    {
        return ['value' => $this->value];
    }

    /**
     * Built from the rows: a time is a time, and a number that leaves its
     * bounds is refused instead of quietly breaking a job at 03:00.
     */
    protected function getValidationRules(?int $excludeId = null): array
    {
        $rules = [];

        foreach (PlatformSetting::board() as $setting) {
            $rules['value.'.$setting->id] = match ($setting->type) {
                'time' => ['required', 'date_format:H:i'],
                'weekday' => ['required', 'integer', 'between:1,7'],
                'decimal' => array_filter(['required', 'numeric', $this->floor($setting), $this->ceiling($setting)]),
                default => array_filter(['required', 'integer', $this->floor($setting), $this->ceiling($setting)]),
            };
        }

        return $rules;
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return PlatformSetting::board()
            ->mapWithKeys(fn (PlatformSetting $s): array => ['value.'.$s->id => __('admin.settings.keys.'.$s->key.'.label')])
            ->all();
    }

    private function floor(PlatformSetting $setting): ?string
    {
        return $setting->min_value === null ? null : 'min:'.(int) $setting->min_value;
    }

    private function ceiling(PlatformSetting $setting): ?string
    {
        return $setting->max_value === null ? null : 'max:'.(int) $setting->max_value;
    }
}
