<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\AiUsage;
use App\Models\Business;
use App\Models\ConversationMessage;
use App\Models\PlatformFigure;
use Illuminate\Support\Collection;

/**
 * Works out, ahead of time, which businesses move the most: the admin home
 * reads the stored result instead of grouping every message on each open.
 *
 * The scheduler runs it every `EVERY_MINUTES`; the screen calls the figure
 * late once it is older than `STALE_AFTER_MINUTES`, so a dead job shows up as
 * an old number with its age, never as a fresh-looking one.
 */
class RankBusinessActivity
{
    public const int EVERY_MINUTES = 60;

    /** Two runs missed and a bit more: a single slow run is not an alarm. */
    public const int STALE_AFTER_MINUTES = 150;

    /** Past one run's slack and before the alarm: the dot turns amber here. */
    public const int LATE_AFTER_MINUTES = 90;

    public const int WINDOW_DAYS = 30;

    private const int TOP = 5;

    /**
     * How deep the previous period is ranked. Deeper than the top on purpose:
     * a business that was 7th and is now 2nd has to be placed, and only one
     * that was outside this depth is called new.
     */
    private const int PREVIOUS_DEPTH = 50;

    public function handle(): PlatformFigure
    {
        $since = now()->subDays(self::WINDOW_DAYS);
        $before = now()->subDays(self::WINDOW_DAYS * 2);

        $whatsapp = ConversationMessage::inboundByBusiness($since, self::TOP);
        $ai = AiUsage::callsByBusiness($since, self::TOP);

        $names = Business::withPlansFor($whatsapp->merge($ai)->pluck('business_id')->unique());

        return PlatformFigure::put(PlatformFigure::ACTIVITY, [
            'days' => self::WINDOW_DAYS,
            'whatsapp' => $this->rows(
                $whatsapp,
                $names,
                ConversationMessage::inboundByBusiness($before, self::PREVIOUS_DEPTH, $since),
            ),
            'ai' => $this->rows(
                $ai,
                $names,
                AiUsage::callsByBusiness($before, self::PREVIOUS_DEPTH, $since),
            ),
        ]);
    }

    /**
     * `previous_rank` is the place it held in the period before, or null when
     * it was not ranked then: that is "new", which is not the same as "same".
     *
     * @param  Collection<int, object{business_id: int, total: int}>  $ranked
     * @param  Collection<int, Business>  $names
     * @param  Collection<int, object{business_id: int, total: int}>  $previous
     * @return list<array{business_id: int, name: string, total: int, rank: int, previous_rank: int|null}>
     */
    private function rows(Collection $ranked, Collection $names, Collection $previous): array
    {
        $before = $previous->pluck('business_id')->values()->flip();

        return $ranked
            ->filter(fn (object $row): bool => $names->has($row->business_id))
            ->values()
            ->map(fn (object $row, int $index): array => [
                'business_id' => $row->business_id,
                'name' => (string) $names->get($row->business_id)->name,
                'total' => $row->total,
                'rank' => $index + 1,
                'previous_rank' => $before->has($row->business_id) ? $before->get($row->business_id) + 1 : null,
            ])
            ->all();
    }
}
