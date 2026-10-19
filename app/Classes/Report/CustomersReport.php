<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Classes\Main\Client;
use App\Classes\Main\Directory;
use App\Enums\ReportAudience;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The "Clientes" screen as a file: the same directory, cut by the same search and
 * audience filter, or all of it when the person asked for everything.
 */
class CustomersReport extends ListReport
{
    public string $title {
        get => __('reports.customers.title');
    }

    public string $filename {
        get => __('reports.customers.filename');
    }

    public array $columns {
        get => [
            __('reports.customers.name'),
            __('reports.customers.phone'),
            __('reports.customers.email'),
            __('reports.customers.last_activity'),
            __('reports.customers.conversations'),
            __('reports.customers.opt_in'),
            __('reports.customers.birthday'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Client;
    }

    public int $total {
        get => $this->directory()?->customers->count() ?? 0;
    }

    public function authorize(User $user): bool
    {
        return $user->can('access-client-app') && $user->business !== null;
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->customers($scope)
            ->map(fn (Customer $customer): array => [
                (string) ($customer->displayName() ?? __('reports.customers.anonymous')),
                $customer->phone,
                (string) $customer->email,
                (string) $customer->last_activity_at?->inBusinessTime()->format('d/m/Y H:i'),
                (int) $customer->conversations_count,
                $customer->marketing_opt_in_at !== null ? __('reports.customers.yes') : __('reports.customers.no'),
                (string) $customer->birthday?->format('d/m'),
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->customers($scope)->count();
    }

    protected function filters(ReportScope $scope): array
    {
        $group = match ($scope->value('filtro')) {
            'optin' => __('client.customers.filter_optin'),
            'birthday' => __('client.customers.filter_birthday'),
            default => '',
        };

        return collect([
            ['label' => __('reports.customers.filter_search'), 'value' => $scope->value('buscar') === '' ? '' : '«'.$scope->value('buscar').'»'],
            ['label' => __('reports.customers.filter_group'), 'value' => $group],
        ])->filter(fn (array $filter): bool => $filter['value'] !== '')->values()->all();
    }

    /** @return Collection<int, Customer> */
    private function customers(ReportScope $scope): Collection
    {
        return $this->directory()?->filtered($scope->value('buscar'), $scope->value('filtro')) ?? new Collection;
    }

    private function directory(): ?Directory
    {
        return Client::for(auth()->user())->directory;
    }
}
