<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Classes\Main\Client;
use App\Enums\ReportAudience;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** Who works next to the assistant: the owner and the agents, with their departments. The screen has no filter. */
class TeamReport extends ListReport
{
    public string $title {
        get => __('reports/account.team.title');
    }

    public string $filename {
        get => __('reports/account.team.filename');
    }

    public array $columns {
        get => [
            __('reports/account.team.name'),
            __('reports/account.team.email'),
            __('reports/account.team.role'),
            __('reports/account.team.departments'),
            __('reports/account.team.open_threads'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Client;
    }

    public int $total {
        get => $this->members()->count();
    }

    public function authorize(User $user): bool
    {
        return $user->can('access-client-app') && $user->can('manage-business') && $user->business !== null;
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->members()
            ->map(fn (User $member): array => [
                $member->name,
                $member->email,
                User::roleLabel((string) $member->roles->first()?->name),
                $member->departments->pluck('name')->implode(', '),
                (int) $member->getAttribute('open_threads'),
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->members()->count();
    }

    protected function filters(ReportScope $scope): array
    {
        return [];
    }

    /** @return Collection<int, User> */
    private function members(): Collection
    {
        return Client::for(auth()->user())->team?->members ?? new Collection;
    }
}
