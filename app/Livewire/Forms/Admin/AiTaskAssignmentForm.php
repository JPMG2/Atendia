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
 * Which model, through which key, answers each task, edited in its row.
 *
 * Both selects carry "connection|code": the same model behind two keys is two
 * different choices. Unassigning is a first-class choice: an empty select sends
 * the task back to the pair written in its agent, where every one starts.
 */
class AiTaskAssignmentForm extends BaseForm
{
    /** @var array<int, string> "connection|code" per task id; empty means unassigned. */
    public array $model = [];

    /** @var array<int, string> The same, for the fallback. */
    public array $fallback = [];

    /** The row being saved: the rules only ever look at one of them. */
    public ?int $taskId = null;

    public function setup(): void
    {
        $tasks = AiTask::board();

        $this->model = $tasks->mapWithKeys(fn (array $row): array => [$row['id'] => $row['model'] ?? ''])->all();
        $this->fallback = $tasks->mapWithKeys(fn (array $row): array => [$row['id'] => $row['fallback'] ?? ''])->all();
    }

    /**
     * Every row that changed, saved in one go.
     *
     * The table reads as one decision (which model answers what), so it has
     * one Guardar. All rows are validated BEFORE one is written: half an
     * assignment is worse than none when the pair decides who answers a customer.
     */
    public function saveAll(): NotificationDto
    {
        $changed = AiTask::board()->filter(fn (array $row): bool => $this->differs($row));

        if ($changed->isEmpty()) {
            return new NotificationDto(__('admin.ai.nothing_changed'), NotificationType::Info);
        }

        $payloads = [];

        foreach ($changed as $row) {
            $this->taskId = $row['id'];
            $payloads[$row['id']] = $this->columns($this->validateServiceData());
        }

        return $this->tryAction(function () use ($payloads): NotificationDto {
            foreach ($payloads as $id => $data) {
                AiTask::find($id)?->update($data);
            }

            $this->setup();

            return new NotificationDto(
                trans_choice('admin.ai.saved_count', count($payloads), ['count' => count($payloads)]),
                NotificationType::Success,
            );
        }, __('notifications.not_updated'));
    }

    /**
     * Whether what is on screen for this task is not what is stored.
     *
     * @param  array<string, mixed>  $row
     */
    private function differs(array $row): bool
    {
        return (string) ($this->model[$row['id']] ?? '') !== (string) ($row['model'] ?? '')
            || (string) ($this->fallback[$row['id']] ?? '') !== (string) ($row['fallback'] ?? '');
    }

    /**
     * The two validated pairs, split into the columns they live in.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, string|null>
     */
    private function columns(array $validated): array
    {
        [$connection, $code] = $this->split($validated['model'] ?? null);
        [$fallbackConnection, $fallbackCode] = $this->split($validated['fallback'] ?? null);

        return [
            'connection_key' => $connection,
            'model_code' => $code,
            'fallback_connection_key' => $fallbackConnection,
            'fallback_model_code' => $fallbackCode,
        ];
    }

    protected function transformServiceData(): array
    {
        $model = $this->pairIn($this->model);

        return [
            'model' => $model,
            // A fallback with no model to fall back FROM is noise: cleared here
            // instead of sitting in the row waiting to confuse the next read.
            'fallback' => $model === null ? null : $this->pairIn($this->fallback),
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        $needs = AiTask::find($this->taskId)?->capability;

        return [
            'model' => ['nullable', 'string', Rule::in($needs === null ? [] : array_keys(AiModel::assignable($needs)))],
            // Another KEY: the ladder is keyed by connection, so a second model
            // behind the same one would silently replace the first.
            'fallback' => ['nullable', 'string', Rule::in($needs === null ? [] : array_keys(
                AiModel::assignable($needs, $this->split($this->pairIn($this->model))[0]),
            ))],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'model' => __('admin.ai.model'),
            'fallback' => __('admin.ai.fallback'),
        ];
    }

    /**
     * The pair chosen for the row being saved, or null when it is empty.
     *
     * @param  array<int, string>  $values
     */
    private function pairIn(array $values): ?string
    {
        $pair = trim((string) ($values[$this->taskId] ?? ''));

        return $pair === '' ? null : $pair;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function split(?string $pair): array
    {
        return $pair === null ? [null, null] : explode('|', $pair, 2) + [null, null];
    }
}
