<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Classes\Main\Agenda;
use App\Classes\Main\Client;
use App\Enums\ReportAudience;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The "Agenda" screen as a file: the bookings of the day or week on screen, or
 * the business's whole history when everything is asked for.
 */
class AgendaReport extends ListReport
{
    public string $title {
        get => __('reports/agenda.title');
    }

    public string $filename {
        get => __('reports/agenda.filename');
    }

    public array $columns {
        get => [
            __('reports/agenda.date'),
            __('reports/agenda.time'),
            __('reports/agenda.customer'),
            __('reports/agenda.phone'),
            __('reports/agenda.service'),
            __('reports/agenda.duration'),
            __('reports/agenda.status'),
            __('reports/agenda.notes'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Client;
    }

    public int $total {
        get => $this->agenda()?->everything->count() ?? 0;
    }

    public function authorize(User $user): bool
    {
        return $user->can('access-client-app') && $user->can('manage-business') && $user->business !== null;
    }

    protected function rows(ReportScope $scope): array
    {
        $zone = (string) $this->agenda()?->today->getTimezone()->getName();

        return $this->bookings($scope)
            ->map(fn (Appointment $appointment): array => [
                $appointment->starts_at->setTimezone($zone)->format('d/m/Y'),
                $appointment->starts_at->setTimezone($zone)->format('H:i'),
                (string) ($appointment->customer?->displayName() ?? __('reports/agenda.anonymous')),
                (string) $appointment->customer?->phone,
                (string) $appointment->service?->name,
                (int) $appointment->service?->duration_minutes,
                __('reports/agenda.statuses.'.$appointment->status->value),
                (string) $appointment->notes,
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->bookings($scope)->count();
    }

    protected function filters(ReportScope $scope): array
    {
        $day = $this->day($scope);

        if ($day === null) {
            return [];
        }

        return $scope->value('vista') === 'week'
            ? [['label' => __('reports/agenda.filter_week'), 'value' => $day->startOfWeek()->format('d/m/Y')]]
            : [['label' => __('reports/agenda.filter_day'), 'value' => $day->format('d/m/Y')]];
    }

    /** @return Collection<int, Appointment> */
    private function bookings(ReportScope $scope): Collection
    {
        $day = $this->day($scope);

        if ($day === null) {
            return $this->agenda()?->everything ?? new Collection;
        }

        return $this->agenda()?->inView($day, $scope->value('vista')) ?? new Collection;
    }

    /** The day on screen, or null when everything was asked or the date is not one. */
    private function day(ReportScope $scope): ?CarbonImmutable
    {
        $raw = $scope->value('dia');

        return $raw !== '' && CarbonImmutable::hasFormat($raw, 'Y-m-d') ? CarbonImmutable::createFromFormat('Y-m-d', $raw) : null;
    }

    private function agenda(): ?Agenda
    {
        return Client::for(auth()->user())->agenda;
    }
}
