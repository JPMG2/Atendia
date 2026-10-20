<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Enums\ReportAudience;
use App\Models\User;
use Illuminate\Support\Collection;

/** The people who open the admin panel, with how safe their access is. Never a credential, only the facts. */
class StaffReport extends ListReport
{
    public string $title {
        get => __('reports/admin.staff.title');
    }

    public string $filename {
        get => __('reports/admin.staff.filename');
    }

    public array $columns {
        get => [
            __('reports/admin.staff.name'),
            __('reports/admin.staff.email'),
            __('reports/admin.staff.role'),
            __('reports/admin.staff.state'),
            __('reports/admin.staff.two_factor'),
            __('reports/admin.staff.last_login'),
            __('reports/admin.staff.location'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => User::directoryTotal();
    }

    public function authorize(User $user): bool
    {
        return $user->can('users.view');
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->people($scope)
            ->map(fn (User $person): array => [
                $person->name,
                $person->email,
                $person->roles->pluck('name')->map(fn (string $role): string => User::roleLabel($role))->implode(', '),
                __('admin.users.states.'.$person->accessState),
                $person->two_factor_whatsapp_at !== null ? __('reports/admin.yes') : __('reports/admin.no'),
                (string) $person->latestLogin?->created_at->format('d/m/Y H:i'),
                (string) $person->latestLogin?->location,
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->people($scope)->count();
    }

    protected function filters(ReportScope $scope): array
    {
        return collect([
            ['label' => __('reports/admin.search'), 'value' => $scope->value('buscar') === '' ? '' : '«'.$scope->value('buscar').'»'],
            ['label' => __('reports/admin.staff.role'), 'value' => $scope->value('rol') === '' ? '' : User::roleLabel($scope->value('rol'))],
            ['label' => __('reports/admin.state'), 'value' => $scope->value('estado') === '' ? '' : __('admin.users.states.'.$scope->value('estado'))],
            ['label' => __('reports/admin.staff.two_factor'), 'value' => match ($scope->value('doble')) {
                'on' => __('reports/admin.yes'),
                'off' => __('reports/admin.no'),
                default => '',
            }],
        ])->filter(fn (array $filter): bool => $filter['value'] !== '')->values()->all();
    }

    /** @return Collection<int, User> */
    private function people(ReportScope $scope): Collection
    {
        return User::directory($scope->value('buscar'), $scope->value('estado'), $scope->value('doble'), $scope->value('rol'));
    }
}
