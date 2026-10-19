<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Enums\ReportAudience;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Collection;

/** The collection queue as a file: who to ring, worth first, with what each account is worth a month. */
class CollectionsReport extends ListReport
{
    public string $title {
        get => __('reports/admin.collections.title');
    }

    public string $filename {
        get => __('reports/admin.collections.filename');
    }

    public array $columns {
        get => [
            __('reports/admin.collections.business'),
            __('reports/admin.collections.plan'),
            __('reports/admin.collections.cycle'),
            __('reports/admin.collections.monthly'),
            __('reports/admin.collections.state'),
            __('reports/admin.collections.since'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => $this->queue()->count();
    }

    public function authorize(User $user): bool
    {
        return $user->can('payments.view');
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->queue()
            ->map(fn (Subscription $row): array => [
                (string) $row->business?->name,
                __('plan.names.'.$row->plan),
                __('collections.cycle_'.$row->billing_cycle),
                round($row->monthlyValue(), 2),
                match ($row->status) {
                    SubscriptionStatus::Paused => __('collections.states.paused'),
                    SubscriptionStatus::PastDue => __('collections.states.grace'),
                    default => __('collections.states.renewing'),
                },
                (string) $row->periodEndsAt()?->format('d/m/Y'),
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->queue()->count();
    }

    protected function filters(ReportScope $scope): array
    {
        return [];
    }

    /** @return Collection<int, Subscription> */
    private function queue(): Collection
    {
        return Subscription::collectionQueue();
    }
}
