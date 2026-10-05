<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\AiModel;
use App\Models\AiTask;
use Illuminate\Validation\Rule;

/**
 * Which model answers each task, edited in the row it belongs to.
 *
 * Unassigning is a first-class choice: an empty select sends the task back to
 * the pair written in its agent, which is where every one of them starts.
 */
class AiTaskAssignmentForm extends BaseForm
{
    /** @var array<int, string> Model code per task id; empty means unassigned. */
    public array $model = [];

    /** @var array<int, string> Fallback code per task id. */
    public array $fallback = [];

    /** The row being saved: the rules only ever look at one of them. */
    public ?int $taskId = null;

    public function setup(): void
    {
        $tasks = AiTask::board();

        $this->model = $tasks->mapWithKeys(fn (array $row): array => [$row['id'] => $row['model'] ?? ''])->all();
        $this->fallback = $tasks->mapWithKeys(fn (array $row): array => [$row['id'] => $row['fallback'] ?? ''])->all();
    }

    public function save(int $taskId): NotificationDto
    {
        $this->taskId = $taskId;
        $validated = $this->validateServiceData();
        $task = AiTask::find($taskId);

        if ($task === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        return $this->tryAction(function () use ($task, $validated): NotificationDto {
            $task->update($validated);
            $this->setup();

            return new NotificationDto(__('admin.ai.saved'), NotificationType::Success);
        }, __('notifications.not_updated'));
    }

    protected function transformServiceData(): array
    {
        $model = $this->codeIn($this->model);

        return [
            'model_code' => $model,
            // A fallback with no model to fall back FROM is noise: cleared here
            // instead of sitting in the row waiting to confuse the next read.
            'fallback_model_code' => $model === null ? null : $this->codeIn($this->fallback),
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'model_code' => ['nullable', 'string', Rule::in(array_keys(AiModel::assignable()))],
            // Only another lab: the ladder is keyed by provider, so a second
            // model of the same one would silently replace the first.
            'fallback_model_code' => ['nullable', 'string', Rule::in(array_keys(
                AiModel::assignable(AiModel::providerOf($this->codeIn($this->model))),
            ))],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'model_code' => __('admin.ai.model'),
            'fallback_model_code' => __('admin.ai.fallback'),
        ];
    }

    /**
     * The code chosen for the row being saved, or null when it is empty.
     *
     * @param  array<int, string>  $values
     */
    private function codeIn(array $values): ?string
    {
        $code = trim((string) ($values[$this->taskId] ?? ''));

        return $code === '' ? null : $code;
    }
}
