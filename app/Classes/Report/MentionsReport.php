<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Enums\MentionStatus;
use App\Enums\ReportAudience;
use App\Models\BusinessMention;
use App\Models\User;
use Illuminate\Support\Collection;

/** The mentions desk as a file: the stage on screen, or the four stages of the desk when all is asked. */
class MentionsReport extends ListReport
{
    /** The stages the desk shows; withdrawn and dismissed are history it never lists. */
    private const STAGES = [MentionStatus::Pending, MentionStatus::NotFollowing, MentionStatus::Ready, MentionStatus::Published];

    public string $title {
        get => __('reports/admin.mentions.title');
    }

    public string $filename {
        get => __('reports/admin.mentions.filename');
    }

    public array $columns {
        get => [
            __('reports/admin.mentions.business'),
            __('reports/admin.mentions.network'),
            __('reports/admin.mentions.status'),
            __('reports/admin.mentions.goal'),
            __('reports/admin.mentions.account'),
            __('reports/admin.mentions.answered'),
            __('reports/admin.mentions.published'),
            __('reports/admin.mentions.post'),
        ];
    }

    public ReportAudience $audience {
        get => ReportAudience::Platform;
    }

    public int $total {
        get => $this->mentions(ReportScope::fromArray([]))->count();
    }

    public function authorize(User $user): bool
    {
        return $user->can('mentions.manage');
    }

    protected function rows(ReportScope $scope): array
    {
        return $this->mentions($scope)
            ->map(fn (BusinessMention $mention): array => [
                (string) $mention->business?->name,
                (string) $mention->socialNetwork?->name,
                $mention->status->label(),
                $mention->goal_threshold,
                (string) $mention->business_account_url,
                (string) $mention->answered_at?->format('d/m/Y H:i'),
                (string) $mention->published_at?->format('d/m/Y H:i'),
                (string) $mention->post_url,
            ])
            ->all();
    }

    protected function shown(ReportScope $scope): int
    {
        return $this->mentions($scope)->count();
    }

    protected function filters(ReportScope $scope): array
    {
        $stage = MentionStatus::tryFrom($scope->value('ver'));

        return $stage === null ? [] : [['label' => __('reports/admin.mentions.status'), 'value' => $stage->label()]];
    }

    /** @return Collection<int, BusinessMention> */
    private function mentions(ReportScope $scope): Collection
    {
        $stage = MentionStatus::tryFrom($scope->value('ver'));

        return collect($stage === null ? self::STAGES : [$stage])
            ->flatMap(fn (MentionStatus $each): Collection => BusinessMention::desk($each, PHP_INT_MAX))
            ->values();
    }
}
