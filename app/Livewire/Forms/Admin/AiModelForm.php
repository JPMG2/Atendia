<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Dto\NotificationDto;
use App\Enums\AiCapability;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\AiConnection;
use App\Models\AiModel;
use App\Rules\AttributeValidator;
use Illuminate\Validation\Rule;

/**
 * A model and what it costs from a day on.
 *
 * A new price is a NEW row, never an edit of the old one: last month has to
 * keep being valued at last month's price. Editing an existing row is for
 * fixing a typo in it, which is why the pair code + date is unique.
 */
class AiModelForm extends BaseForm
{
    public ?int $id = null;

    public string $provider = 'openai';

    public string $capability = 'text';

    public string $code = '';

    public string $label = '';

    public string $prompt_per_million = '';

    public string $cached_per_million = '';

    public string $completion_per_million = '';

    public string $per_minute = '';

    public string $effective_from = '';

    public string $source = '';

    public bool $is_active = true;

    public function setup(?AiModel $model = null): void
    {
        $this->reset();

        if ($model === null) {
            $this->effective_from = now()->toDateString();

            return;
        }

        $this->id = $model->id;
        $this->provider = $model->provider;
        $this->capability = $model->capability->value;
        $this->code = $model->code;
        $this->label = $model->label;
        $this->prompt_per_million = (string) $model->prompt_per_million;
        $this->cached_per_million = (string) $model->cached_per_million;
        $this->completion_per_million = (string) $model->completion_per_million;
        $this->per_minute = (string) $model->per_minute;
        $this->effective_from = $model->effective_from->toDateString();
        $this->source = (string) $model->source;
        $this->is_active = $model->is_active;
    }

    public function save(): NotificationDto
    {
        $validated = $this->validateServiceData($this->id);

        return $this->tryAction(function () use ($validated): NotificationDto {
            $model = $this->id === null ? new AiModel : AiModel::priceRow($this->id);
            $model->fill($validated)->save();
            $this->setup($model);

            return new NotificationDto(__('admin.ai.model_saved'), NotificationType::Success);
        }, __('notifications.not_updated'));
    }

    /** The unit this model is billed in; an unknown value falls back to text. */
    private function billing(): AiCapability
    {
        return AiCapability::tryFrom($this->capability) ?? AiCapability::Text;
    }

    protected function transformServiceData(): array
    {
        $billing = $this->billing();

        return [
            'provider' => $this->provider,
            'capability' => $this->capability,
            'code' => trim($this->code),
            'label' => trim($this->label),
            // The fields a capability does not bill stay at zero, never at a
            // leftover from a previous choice in the same form.
            'prompt_per_million' => $billing->billsPerMinute() ? '0' : $this->prompt_per_million,
            'cached_per_million' => $billing->billsOutput() ? $this->cached_per_million : '0',
            'completion_per_million' => $billing->billsOutput() ? $this->completion_per_million : '0',
            'per_minute' => $billing->billsPerMinute() ? $this->per_minute : null,
            'effective_from' => $this->effective_from,
            'source' => trim($this->source) === '' ? null : trim($this->source),
            'is_active' => $this->is_active,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        $billing = $this->billing();

        return [
            // Only a lab with a key behind it: a model nobody can call is noise.
            // The one already stored stays valid so a typo can still be fixed.
            'provider' => ['required', Rule::in([...array_keys(AiConnection::drivers()), ...($excludeId === null ? [] : [$this->provider])])],

            'capability' => ['required', Rule::enum(AiCapability::class)],

            'code' => [
                ...AttributeValidator::stringValid(true, '2'),
                'max:80',
                Rule::unique('ai_models', 'code')
                    ->where('effective_from', $this->effective_from)
                    ->ignore($excludeId),
            ],

            'label' => [...AttributeValidator::stringValid(true, '2'), 'max:60'],

            'prompt_per_million' => AttributeValidator::numericDecimal(! $billing->billsPerMinute()),
            'cached_per_million' => AttributeValidator::numericDecimal($billing->billsOutput()),
            'completion_per_million' => AttributeValidator::numericDecimal($billing->billsOutput()),
            'per_minute' => AttributeValidator::numericDecimal($billing->billsPerMinute()),

            // ISO, because that is what the datepicker's hidden field carries.
            'effective_from' => ['required', 'date_format:Y-m-d'],

            'source' => ['nullable', 'string', 'max:255', AttributeValidator::xssFree()],
            'is_active' => AttributeValidator::booleanValue(true),
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'provider' => __('admin.ai.fields.provider'),
            'capability' => __('admin.ai.fields.capability'),
            'code' => __('admin.ai.fields.code'),
            'label' => __('admin.ai.fields.label'),
            'prompt_per_million' => __('admin.ai.fields.prompt'),
            'cached_per_million' => __('admin.ai.fields.cached'),
            'completion_per_million' => __('admin.ai.fields.completion'),
            'per_minute' => __('admin.ai.fields.per_minute'),
            'effective_from' => __('admin.ai.fields.effective_from'),
            'source' => __('admin.ai.fields.source'),
            'is_active' => __('admin.ai.fields.status'),
        ];
    }
}
