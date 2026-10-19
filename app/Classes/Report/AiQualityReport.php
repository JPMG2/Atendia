<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Enums\ReportAudience;
use App\Models\AssistantRating;
use App\Models\User;
use Illuminate\Support\Collection;

/** How each business's assistant is being marked: good and bad replies, and the share that were good. */
class AiQualityReport extends ListReport
{
    public string $title {
        get => __('reports/admin.ai_quality.title');
    }

    public string $filename {
        get => __('reports/admin.ai_quality.filename');
    }

    public array $columns {
        get => [
            __('reports/admin.ai_quality.business'),
            __('reports/admin.ai_quality.good'),
            __('reports/admin.ai_quality.bad'),
            __('reports/admin.ai_quality.total'),
            __('reports/admin.ai_quality.share'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => $this->boards()->count();
    }

    public function authorize(User $user): bool
    {
        return $user->can('ai.view');
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->boards()
            ->map(fn (object $row): array => [
                $row->name,
                (int) $row->good,
                (int) $row->bad,
                (int) $row->total,
                (int) $row->total > 0 ? round((int) $row->good / (int) $row->total * 100, 1) : '',
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

    /** @return Collection<int, object> */
    private function boards(): Collection
    {
        return AssistantRating::byBusiness();
    }
}
