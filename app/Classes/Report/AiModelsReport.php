<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Enums\ReportAudience;
use App\Models\AiModel;
use App\Models\User;
use Illuminate\Support\Collection;

/** The price list of the models the assistants run on: what each costs per million tokens. */
class AiModelsReport extends ListReport
{
    public string $title {
        get => __('reports/admin.ai_models.title');
    }

    public string $filename {
        get => __('reports/admin.ai_models.filename');
    }

    public array $columns {
        get => [
            __('reports/admin.ai_models.code'),
            __('reports/admin.ai_models.label'),
            __('reports/admin.ai_models.provider'),
            __('reports/admin.ai_models.capability'),
            __('reports/admin.ai_models.prompt'),
            __('reports/admin.ai_models.cached'),
            __('reports/admin.ai_models.completion'),
            __('reports/admin.ai_models.effective'),
            __('reports/admin.ai_models.active'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => $this->models()->count();
    }

    public function authorize(User $user): bool
    {
        return $user->can('ai.manage');
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->models()
            ->map(fn (AiModel $model): array => [
                $model->code,
                (string) $model->label,
                (string) $model->provider,
                (string) ($model->capability instanceof \BackedEnum ? $model->capability->value : $model->capability),
                $model->prompt_per_million !== null ? (float) $model->prompt_per_million : '',
                $model->cached_per_million !== null ? (float) $model->cached_per_million : '',
                $model->completion_per_million !== null ? (float) $model->completion_per_million : '',
                (string) $model->effective_from?->format('d/m/Y'),
                $model->is_active ? __('reports/admin.yes') : __('reports/admin.no'),
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->total;
    }

    protected function filters(ReportScope $scope): array
    {
        return [];
    }

    /** @return Collection<int, AiModel> */
    private function models(): Collection
    {
        return AiModel::board();
    }
}
