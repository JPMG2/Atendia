<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
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

    public string $code = '';

    public string $label = '';

    public string $prompt_per_million = '';

    public string $cached_per_million = '';

    public string $completion_per_million = '';

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
        $this->code = $model->code;
        $this->label = $model->label;
        $this->prompt_per_million = (string) $model->prompt_per_million;
        $this->cached_per_million = (string) $model->cached_per_million;
        $this->completion_per_million = (string) $model->completion_per_million;
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

    protected function transformServiceData(): array
    {
        return [
            'provider' => $this->provider,
            'code' => trim($this->code),
            'label' => trim($this->label),
            'prompt_per_million' => $this->prompt_per_million,
            'cached_per_million' => $this->cached_per_million,
            'completion_per_million' => $this->completion_per_million,
            'effective_from' => $this->effective_from,
            'source' => trim($this->source) === '' ? null : trim($this->source),
            'is_active' => $this->is_active,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            // Only a lab this app has credentials for: the select cannot offer
            // a provider that config/ai.php does not define.
            'provider' => ['required', Rule::in(array_keys((array) config('ai.providers')))],

            'code' => [
                ...AttributeValidator::stringValid(true, '2'),
                'max:80',
                Rule::unique('ai_models', 'code')
                    ->where('effective_from', $this->effective_from)
                    ->ignore($excludeId),
            ],

            'label' => [...AttributeValidator::stringValid(true, '2'), 'max:60'],

            'prompt_per_million' => AttributeValidator::numericDecimal(true),
            'cached_per_million' => AttributeValidator::numericDecimal(true),
            'completion_per_million' => AttributeValidator::numericDecimal(true),

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
            'code' => __('admin.ai.fields.code'),
            'label' => __('admin.ai.fields.label'),
            'prompt_per_million' => __('admin.ai.fields.prompt'),
            'cached_per_million' => __('admin.ai.fields.cached'),
            'completion_per_million' => __('admin.ai.fields.completion'),
            'effective_from' => __('admin.ai.fields.effective_from'),
            'source' => __('admin.ai.fields.source'),
            'is_active' => __('admin.ai.fields.status'),
        ];
    }
}
