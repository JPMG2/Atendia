<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Enums\ReportAudience;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Collection;

/** The admin's ledger of businesses, cut by the same search and state as the screen. */
class BusinessesReport extends ListReport
{
    public string $title {
        get => __('reports/admin.businesses.title');
    }

    public string $filename {
        get => __('reports/admin.businesses.filename');
    }

    public array $columns {
        get => [
            __('reports/admin.businesses.name'),
            __('reports/admin.businesses.plan'),
            __('reports/admin.businesses.situation'),
            __('reports/admin.businesses.due'),
            __('reports/admin.businesses.owes'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => Business::directoryTotal();
    }

    public function authorize(User $user): bool
    {
        return $user->can('businesses.view');
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->businesses($scope)
            ->map(fn (Business $business): array => [
                $business->name,
                $business->subscription ? __('plan.names.'.$business->subscription->plan) : '',
                __('admin.businesses.states.'.$business->situation()),
                (string) $business->subscription?->periodEndsAt()?->format('d/m/Y'),
                $business->owes() > 0 ? round($business->owes(), 2) : '',
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return Business::directoryMatching($scope->value('buscar'), $scope->value('estado'));
    }

    protected function filters(ReportScope $scope): array
    {
        return collect([
            ['label' => __('reports/admin.search'), 'value' => $scope->value('buscar') === '' ? '' : '«'.$scope->value('buscar').'»'],
            ['label' => __('reports/admin.state'), 'value' => $scope->value('estado') === '' ? '' : __('admin.businesses.states.'.$scope->value('estado'))],
        ])->filter(fn (array $filter): bool => $filter['value'] !== '')->values()->all();
    }

    /** @return Collection<int, Business> */
    private function businesses(ReportScope $scope): Collection
    {
        return Business::directory($scope->value('buscar'), $scope->value('estado'));
    }
}
