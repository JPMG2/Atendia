<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Enums\ReportAudience;
use App\Models\ModerationFlag;
use App\Models\User;
use Illuminate\Support\Collection;

/** Everything the content filter caught, reviewed or not. Evidence is a fingerprint only: no file is ever listed. */
class ModerationReport extends ListReport
{
    public string $title {
        get => __('reports/admin.moderation.title');
    }

    public string $filename {
        get => __('reports/admin.moderation.filename');
    }

    public array $columns {
        get => [
            __('reports/admin.moderation.date'),
            __('reports/admin.moderation.business'),
            __('reports/admin.moderation.source'),
            __('reports/admin.moderation.kind'),
            __('reports/admin.moderation.severity'),
            __('reports/admin.moderation.category'),
            __('reports/admin.moderation.score'),
            __('reports/admin.moderation.reviewed'),
            __('reports/admin.moderation.reviewer'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => $this->flags()->count();
    }

    public function authorize(User $user): bool
    {
        return $user->can('moderation.view');
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->flags()
            ->map(fn (ModerationFlag $flag): array => [
                $flag->created_at->format('d/m/Y H:i'),
                (string) $flag->business?->name,
                (string) $flag->source,
                (string) $flag->kind,
                (string) ($flag->severity instanceof \BackedEnum ? $flag->severity->value : $flag->severity),
                (string) $flag->category,
                $flag->score !== null ? round((float) $flag->score, 3) : '',
                (string) $flag->reviewed_at?->format('d/m/Y H:i'),
                (string) $flag->reviewer?->name,
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->total;
    }

    protected function filters(ReportScope $scope): array
    {
        return [];
    }

    /** @return Collection<int, ModerationFlag> */
    private function flags(): Collection
    {
        return ModerationFlag::ledger();
    }
}
