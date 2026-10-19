<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Classes\Main\Incidents;
use App\Dto\IncidentRowDto;
use App\Enums\IncidentKind;
use App\Enums\ReportAudience;
use App\Models\User;
use Illuminate\Support\Collection;

/** "Qué salió mal" as a file: the tab on screen, or every kind of incident when all is asked. */
class IncidentsReport extends ListReport
{
    public string $title {
        get => __('reports/admin.incidents.title');
    }

    public string $filename {
        get => __('reports/admin.incidents.filename');
    }

    public array $columns {
        get => [
            __('reports/admin.incidents.kind'),
            __('reports/admin.incidents.when'),
            __('reports/admin.incidents.business'),
            __('reports/admin.incidents.customer'),
            __('reports/admin.incidents.waiting'),
            __('reports/admin.incidents.excerpt'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => $this->incidents(ReportScope::fromArray([]))->count();
    }

    public function authorize(User $user): bool
    {
        return $user->can('incidents.view');
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->incidents($scope)
            ->map(fn (IncidentRowDto $row): array => [
                __('incidents.kinds.'.$row->kind->value.'.tab'),
                $row->happenedAt->format('d/m/Y H:i'),
                (string) $row->business,
                (string) $row->customer,
                $row->minutesWaiting,
                (string) $row->excerpt,
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->incidents($scope)->count();
    }

    protected function filters(ReportScope $scope): array
    {
        $kind = IncidentKind::tryFrom($scope->value('tipo'));

        return $kind === null ? [] : [['label' => __('reports/admin.incidents.kind'), 'value' => __('incidents.kinds.'.$kind->value.'.tab')]];
    }

    /** @return Collection<int, IncidentRowDto> */
    private function incidents(ReportScope $scope): Collection
    {
        return Incidents::now()->ofKind(IncidentKind::tryFrom($scope->value('tipo')));
    }
}
