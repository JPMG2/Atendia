<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Actions\Embeddings\StartEmbeddingMigration;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\AiModel;
use DomainException;
use Illuminate\Validation\Rule;

/** The model the knowledge search is about to move to. */
class EmbeddingMigrationForm extends BaseForm
{
    /** "code|dimensions": the same model at two lengths is two different spaces. */
    public string $target = '';

    public function setup(): void
    {
        $this->reset();
    }

    public function save(): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated): NotificationDto {
            [$model, $dimensions] = explode('|', $validated['target']);

            try {
                app(StartEmbeddingMigration::class)->handle($model, (int) $dimensions, auth()->user());
            } catch (DomainException $refusal) {
                // A refusal is an answer, not a failure: nothing to report.
                return new NotificationDto(__('admin.ai.embeddings.errors.'.$refusal->getMessage()), NotificationType::Warning);
            }

            $this->setup();

            return new NotificationDto(__('admin.ai.embeddings.started'), NotificationType::Success);
        }, __('notifications.not_updated'));
    }

    protected function transformServiceData(): array
    {
        return ['target' => $this->target];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'target' => ['required', Rule::in(array_keys(AiModel::embeddingChoices()))],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return ['target' => __('admin.ai.embeddings.target')];
    }
}
