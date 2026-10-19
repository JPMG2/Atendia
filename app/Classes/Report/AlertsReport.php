<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Enums\PlatformEventSeverity;
use App\Enums\ReportAudience;
use App\Models\PlatformEvent;
use App\Models\User;
use Illuminate\Support\Collection;

/** The alerts board as a file: what is waiting, worst first, cut by the severity the screen is on. */
class AlertsReport extends ListReport
{
    public string $title {
        get => __('reports/admin.alerts.title');
    }

    public string $filename {
        get => __('reports/admin.alerts.filename');
    }

    public array $columns {
        get => [
            __('reports/admin.alerts.severity'),
            __('reports/admin.alerts.what'),
            __('reports/admin.alerts.business'),
            __('reports/admin.alerts.state'),
            __('reports/admin.alerts.occurrences'),
            __('reports/admin.alerts.first'),
            __('reports/admin.alerts.last'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => $this->events(ReportScope::fromArray([]))->count();
    }

    public function authorize(User $user): bool
    {
        return $user->can('alerts.view');
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->events($scope)
            ->map(fn (PlatformEvent $event): array => [
                $event->severity->label(),
                $event->kind->label(),
                (string) $event->aboutBusiness?->name,
                $event->state->label(),
                $event->occurrences,
                $event->first_seen_at->format('d/m/Y H:i'),
                $event->last_seen_at->format('d/m/Y H:i'),
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->events($scope)->count();
    }

    protected function filters(ReportScope $scope): array
    {
        $view = $scope->value('ver');
        $label = $view === 'unseen' ? __('reports/admin.alerts.unseen') : PlatformEventSeverity::tryFrom($view)?->label();

        return $label === null ? [] : [['label' => __('reports/admin.alerts.severity'), 'value' => $label]];
    }

    /** @return Collection<int, PlatformEvent> */
    private function events(ReportScope $scope): Collection
    {
        $view = $scope->value('ver');

        return PlatformEvent::board(PHP_INT_MAX, PlatformEventSeverity::tryFrom($view), unseenOnly: $view === 'unseen');
    }
}
