<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Enums\ReportAudience;
use App\Models\ComplianceEvent;
use App\Models\User;
use Illuminate\Support\Collection;

/** The compliance board as a file: businesses with something in their file, cut by the screen's search and standing. */
class ComplianceReport extends ListReport
{
    public string $title {
        get => __('reports/admin.compliance.title');
    }

    public string $filename {
        get => __('reports/admin.compliance.filename');
    }

    public array $columns {
        get => [
            __('reports/admin.compliance.business'),
            __('reports/admin.compliance.standing'),
            __('reports/admin.compliance.recent', ['days' => ComplianceEvent::RECENT_DAYS]),
            __('reports/admin.compliance.total'),
            __('reports/admin.compliance.last'),
            __('reports/admin.compliance.last_kind'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => ComplianceEvent::boardCount();
    }

    public function authorize(User $user): bool
    {
        return $user->can('compliance.view');
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->board($scope)
            ->map(fn (array $row): array => [
                $row['name'],
                __('compliance.standing.'.$row['standing']),
                $row['recent'],
                $row['total'],
                (string) $row['last_at']?->format('d/m/Y H:i'),
                $row['last_kind']?->label() ?? '',
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return ComplianceEvent::boardCount($scope->value('estado'), $scope->value('buscar'));
    }

    protected function filters(ReportScope $scope): array
    {
        return collect([
            ['label' => __('reports/admin.search'), 'value' => $scope->value('buscar') === '' ? '' : '«'.$scope->value('buscar').'»'],
            ['label' => __('reports/admin.state'), 'value' => $scope->value('estado') === '' ? '' : (string) __('compliance.filters.'.$scope->value('estado'))],
        ])->filter(fn (array $filter): bool => $filter['value'] !== '')->values()->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function board(ReportScope $scope): Collection
    {
        return ComplianceEvent::board($scope->value('estado'), $scope->value('buscar'), PHP_INT_MAX);
    }
}
