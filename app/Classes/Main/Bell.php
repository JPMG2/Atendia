<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Enums\PanelNotificationType;
use App\Models\Business;
use App\Models\PanelNotification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The bell piece: what happened to this business, as one person reads it. The
 * web's panel and the mobile API both draw from here, so a notice cannot read
 * one way on the screen and another on the phone.
 */
class Bell
{
    /** Where a repeated kind stops being listed one by one and becomes a count. */
    private const int BUNDLE_FROM = 3;

    public function __construct(private Business $business, private User $user) {}

    /**
     * The number on the bell. Zero keeps it dark, on purpose.
     */
    public int $unread {
        get => PanelNotification::unreadCountFor($this->user);
    }

    /**
     * The feed already cut into the day groups the panel prints, on the
     * business clock. Evaluated on every read: callers keep the result.
     *
     * @var Collection<string, Collection<int, array<string, mixed>>>
     */
    public Collection $groups {
        get {
            $timezone = $this->business->localTimezone();
            $today = now($timezone)->startOfDay();

            return PanelNotification::feedFor($this->user)
                ->groupBy(function (PanelNotification $notice) use ($today, $timezone): string {
                    $days = $notice->localDay($timezone)->diffInDays($today);

                    return match (true) {
                        $days < 1 => 'today',
                        $days < 2 => 'yesterday',
                        default => 'earlier',
                    };
                })
                ->map(fn (Collection $day): Collection => $this->rowsFor($day, $timezone));
        }
    }

    /**
     * The unread in one sentence, above the list of it: whether it is worth
     * reading on before scrolling. Takes the groups so the feed is read once.
     *
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $groups
     */
    public function digestFor(Collection $groups): string
    {
        $counts = $groups
            ->flatten(1)
            ->reject(fn (array $row): bool => $row['read'])
            ->groupBy(fn (array $row): string => $row['type']->value)
            ->map(fn (Collection $rows): int => $rows->sum(fn (array $row): int => count($row['ids'])));

        return __('bell.digest.lead').' '.$counts
            ->map(fn (int $count, string $type): string => trans_choice('bell.digest.'.$type, $count, ['count' => $count]))
            ->join(' · ');
    }

    /**
     * Opening a row is what marks it read: opening the panel is not reading.
     * A bundled row carries every id it stands for.
     *
     * @param  list<int>  $ids
     */
    public function read(array $ids): void
    {
        foreach ($ids as $id) {
            PanelNotification::markOneReadFor((int) $id, $this->user);
        }
    }

    /** The hour on the business clock: a row stamped in UTC reads three hours off. */
    public function timeOf(\DateTimeInterface $at): string
    {
        return Carbon::instance($at)->setTimezone($this->business->localTimezone())->format('H:i');
    }

    /**
     * One day's notices as the rows the panel prints. Three or more of the same
     * kind collapse into a single counted row: a list that repeats the same
     * sentence stops being read, and the screen behind it holds the detail.
     *
     * @param  Collection<int, PanelNotification>  $day
     * @return Collection<int, array<string, mixed>>
     */
    private function rowsFor(Collection $day, string $timezone): Collection
    {
        return $day->groupBy(fn (PanelNotification $notice): string => $notice->type->value)
            ->flatMap(function (Collection $sameKind): Collection {
                return $sameKind->count() < self::BUNDLE_FROM
                    ? $sameKind->map(fn (PanelNotification $notice): array => $this->singleRow($notice))
                    : collect([$this->bundledRow($sameKind)]);
            })
            ->sortByDesc('at')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function singleRow(PanelNotification $notice): array
    {
        return [
            'key' => 'n'.$notice->id,
            'ids' => [$notice->id],
            'type' => $notice->type,
            'text' => __($this->lineFor($notice), $notice->payload),
            'at' => $notice->created_at,
            'read' => (bool) $notice->read,
        ];
    }

    /**
     * A booking taken without choosing a service (the public link allows it)
     * has no tail to print, and the line that names one would end in a comma
     * and nothing. Resolved here, where the reader's locale is the one in use.
     */
    private function lineFor(PanelNotification $notice): string
    {
        $key = 'bell.lines.'.$notice->type->value;
        $plain = $notice->type === PanelNotificationType::AppointmentBooked
            && trim((string) ($notice->payload['service'] ?? '')) === '';

        return $plain ? $key.'_plain' : $key;
    }

    /**
     * @param  Collection<int, PanelNotification>  $sameKind
     * @return array<string, mixed>
     */
    private function bundledRow(Collection $sameKind): array
    {
        $first = $sameKind->first();

        return [
            'key' => 'b'.$first->type->value,
            'ids' => $sameKind->pluck('id')->all(),
            'type' => $first->type,
            'text' => trans_choice('bell.bundles.'.$first->type->value, $sameKind->count(), ['count' => $sameKind->count()]),
            'at' => $sameKind->max('created_at'),
            // Bundled reads as unread while ANY of them is: the count is the news.
            'read' => $sameKind->every(fn (PanelNotification $notice): bool => (bool) $notice->read),
        ];
    }
}
