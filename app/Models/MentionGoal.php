<?php

declare(strict_types=1);

namespace App\Models;

use App\Dto\CatalogColumnDto;
use App\Enums\CatalogCell;
use App\Interfaces\Catalog\DataTable;
use App\Interfaces\Catalog\HasColumns;
use App\Interfaces\Catalog\Removable;
use App\Traits\CountsUsage;
use App\Traits\EditedByForm;
use App\Traits\TracksUserActions;
use Database\Factories\MentionGoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * A milestone that earns a business a mention: conversations answered in a
 * month, and the words of the post. The metric lives in code; she owns the
 * numbers and the wording.
 */
#[Fillable(['threshold', 'caption', 'is_active'])]
class MentionGoal extends Model implements DataTable, HasColumns, Removable
{
    use CountsUsage;
    use EditedByForm;

    /** @use HasFactory<MentionGoalFactory> */
    use HasFactory;

    // A master row is never deleted: the audit trail may point at it.
    use SoftDeletes;
    use TracksUserActions;

    /** X is the shortest network we post on: a caption that fits there fits everywhere. */
    public const CAPTION_MAX = 280;

    /** Mentions keep their own copy of the goal, so nothing holds this row. */
    public function usage(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'threshold' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function normalizeCaption(string $value): string
    {
        return trim($value);
    }

    protected function caption(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => self::normalizeCaption($value));
    }

    /**
     * The biggest active goal the business has reached that it has not been
     * asked about yet, so one month's count never opens three offers at once.
     */
    public static function reachedBy(int $conversations, int $above = 0): ?self
    {
        return self::query()
            ->where('is_active', true)
            ->where('threshold', '<=', $conversations)
            ->where('threshold', '>', $above)
            ->orderByDesc('threshold')
            ->first();
    }

    /** The goal as a person reads it: "100 conversaciones atendidas en un mes". */
    public static function achievement(int $threshold): string
    {
        return trans_choice('mentions.achievement', $threshold, ['count' => number_format($threshold, 0, ',', '.')]);
    }

    /** The post, with the business and its achievement written in. */
    public function captionFor(string $businessName): string
    {
        return strtr($this->caption, [
            '{negocio}' => $businessName,
            '{logro}' => self::achievement($this->threshold),
        ]);
    }

    /**
     * @return Collection<int, array{id: int, threshold: int, achievement: string, caption: string, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->orderBy('threshold')
            ->get()
            ->map(fn (self $goal): array => [
                'id' => $goal->id,
                'threshold' => $goal->threshold,
                'achievement' => self::achievement($goal->threshold),
                'caption' => $goal->caption,
                'active' => $goal->is_active,
            ])
            ->values();
    }

    /** The goal column reads `achievement` (the words), not the bare `threshold`. */
    public function catalogColumns(): array
    {
        return [
            new CatalogColumnDto('achievement', __('catalog.mention_goal.columns.threshold'), CatalogCell::Name, search: true),
            new CatalogColumnDto('caption', __('catalog.mention_goal.columns.caption'), CatalogCell::Text, fill: true, search: true),
            new CatalogColumnDto('active', __('catalog.mention_goal.columns.status'), CatalogCell::Active, on: __('catalog.mention_goal.status.active'), off: __('catalog.mention_goal.status.inactive')),
        ];
    }
}
