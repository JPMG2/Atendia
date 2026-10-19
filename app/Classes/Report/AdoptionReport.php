<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Dto\AdoptionRowDto;
use App\Enums\AdoptionSituation;
use App\Enums\ReportAudience;
use App\Models\User;
use Illuminate\Support\Collection;

/** The adoption desk as a file: the accounts of the tab on screen, or every account when all is asked. */
class AdoptionReport extends ListReport
{
    public string $title {
        get => __('reports/admin.adoption.title');
    }

    public string $filename {
        get => __('reports/admin.adoption.filename');
    }

    public array $columns {
        get => [
            __('reports/admin.adoption.owner'),
            __('reports/admin.adoption.email'),
            __('reports/admin.adoption.business'),
            __('reports/admin.adoption.step'),
            __('reports/admin.adoption.situation'),
            __('reports/admin.adoption.days'),
            __('reports/admin.adoption.registered'),
            __('reports/admin.adoption.last_seen'),
            __('reports/admin.adoption.conversations'),
            __('reports/admin.adoption.tickets'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => User::adoptionRows()->count();
    }

    public function authorize(User $user): bool
    {
        return $user->can('adoption.view');
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->accounts($scope)
            ->map(fn (AdoptionRowDto $row): array => [
                $row->owner,
                $row->email,
                (string) $row->business,
                $row->step->label(),
                $row->situation->label(),
                $row->daysInStep,
                $row->registeredAt->format('d/m/Y'),
                (string) $row->lastSeenAt?->format('d/m/Y H:i'),
                $row->conversations,
                $row->tickets,
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->accounts($scope)->count();
    }

    protected function filters(ReportScope $scope): array
    {
        $tab = AdoptionSituation::tryFrom($scope->value('ver'));

        return $tab === null ? [] : [['label' => __('reports/admin.adoption.tab'), 'value' => $tab->label()]];
    }

    /** @return Collection<int, AdoptionRowDto> */
    private function accounts(ReportScope $scope): Collection
    {
        $tab = AdoptionSituation::tryFrom($scope->value('ver'));

        return $tab === null ? User::adoptionRows() : User::adoptionTab($tab);
    }
}
