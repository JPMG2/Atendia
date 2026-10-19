<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Enums\ReportAudience;
use App\Models\SupportTicket;
use App\Models\SupportTicketKind;
use App\Models\User;
use Illuminate\Support\Collection;

/** The support inbox as a file: the queue and filters on screen, or every report ever sent. */
class SupportReport extends ListReport
{
    public string $title {
        get => __('reports/admin.support.title');
    }

    public string $filename {
        get => __('reports/admin.support.filename');
    }

    public array $columns {
        get => [
            __('reports/admin.support.code'),
            __('reports/admin.support.date'),
            __('reports/admin.support.business'),
            __('reports/admin.support.kind'),
            __('reports/admin.support.status'),
            __('reports/admin.support.priority'),
            __('reports/admin.support.assignee'),
            __('reports/admin.support.report'),
            __('reports/admin.support.answered'),
            __('reports/admin.support.resolved'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => $this->tickets(ReportScope::fromArray(['alcance' => 'todo']))->count();
    }

    public function authorize(User $user): bool
    {
        return $user->can('support.view');
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->tickets($scope)
            ->map(fn (SupportTicket $ticket): array => [
                $ticket->code,
                $ticket->created_at->format('d/m/Y H:i'),
                (string) $ticket->business?->name,
                (string) $ticket->kind?->name,
                $ticket->status->adminLabel(),
                $ticket->priority->label(),
                (string) $ticket->assignee?->name,
                $ticket->body,
                (string) $ticket->answered_at?->format('d/m/Y H:i'),
                (string) $ticket->resolved_at?->format('d/m/Y H:i'),
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->tickets($scope)->count();
    }

    protected function filters(ReportScope $scope): array
    {
        $business = $scope->value('negocio');
        $who = $scope->value('responsable');

        return collect([
            ['label' => __('reports/admin.support.queue'), 'value' => $scope->value('cola') === '' ? '' : __('support.admin.tabs.'.$scope->value('cola'))],
            ['label' => __('reports/admin.support.kind'), 'value' => (string) SupportTicketKind::byCode($scope->value('tipo'))?->name],
            ['label' => __('reports/admin.support.business'), 'value' => $business === '' ? '' : (string) (SupportTicket::reporters()[(int) $business] ?? '')],
            ['label' => __('reports/admin.support.assignee'), 'value' => $who === '' ? '' : ($who === 'none' ? __('support.admin.unassigned') : (string) (User::supportTeam()[(int) $who] ?? ''))],
            ['label' => __('reports/admin.search'), 'value' => $scope->value('buscar') === '' ? '' : '«'.$scope->value('buscar').'»'],
        ])->filter(fn (array $filter): bool => $filter['value'] !== '')->values()->all();
    }

    /** @return Collection<int, SupportTicket> */
    private function tickets(ReportScope $scope): Collection
    {
        $queue = $scope->value('cola');
        $kind = $scope->value('tipo');
        $business = $scope->value('negocio');
        $who = $scope->value('responsable');

        return SupportTicket::inbox(
            $queue === '' ? 'all' : $queue,
            $kind === '' ? null : $kind,
            $business === '' ? null : (int) $business,
            $scope->value('buscar'),
            $who === '' ? null : $who,
            limit: PHP_INT_MAX,
        );
    }
}
