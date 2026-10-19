<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Classes\Main\AuditTrail;
use App\Enums\ReportAudience;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/** The audit trail as a file: who did what and on what, with the strong movements only when the screen shows only those. */
class AuditReport extends ListReport
{
    /** Far above what a person scrolls, low enough that one file never loads the whole table. */
    private const LIMIT = 5000;

    public string $title {
        get => __('reports/admin.audit.title');
    }

    public string $filename {
        get => __('reports/admin.audit.filename');
    }

    public array $columns {
        get => [
            __('reports/admin.audit.when'),
            __('reports/admin.audit.who'),
            __('reports/admin.audit.email'),
            __('reports/admin.audit.what'),
            __('reports/admin.audit.on'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => AuditTrail::total();
    }

    public function authorize(User $user): bool
    {
        return $user->can('audit.view');
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->entries($scope)
            ->map(fn (Activity $entry): array => [
                $entry->created_at?->format('d/m/Y H:i') ?? '',
                $entry->causer?->name ?? __('admin.audit.system'),
                (string) $entry->causer?->email,
                __('admin.audit.actions.'.$entry->description),
                AuditTrail::subjectOf($entry),
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->entries($scope)->count();
    }

    protected function filters(ReportScope $scope): array
    {
        $causer = $scope->value('quien');
        $action = $scope->value('accion');

        return collect([
            ['label' => __('admin.audit.who'), 'value' => $causer === '' ? '' : (string) (AuditTrail::causerOptions()[$causer] ?? '')],
            ['label' => __('admin.audit.what'), 'value' => $action === '' ? '' : __('admin.audit.actions.'.$action)],
            ['label' => __('reports/admin.audit.scope'), 'value' => $this->onlyStrong($scope) ? __('reports/admin.audit.strong') : ''],
        ])->filter(fn (array $filter): bool => $filter['value'] !== '')->values()->all();
    }

    /** @return Collection<int, Activity> */
    private function entries(ReportScope $scope): Collection
    {
        return AuditTrail::rows($scope->value('quien'), $this->onlyStrong($scope), self::LIMIT, $scope->value('accion'));
    }

    /** The screen always says which of the two it is on; without it (or asking for everything) nothing is left out. */
    private function onlyStrong(ReportScope $scope): bool
    {
        return $scope->value('fuertes') === '1';
    }
}
