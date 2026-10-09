<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Lang;
use Spatie\Activitylog\Models\Activity;

/**
 * Who did what, and what of it cannot be undone.
 *
 * 757 of the first 848 entries had NO person behind them — the assistant
 * filing suggestions. Opened on everything, this screen is machine noise
 * burying the eighteen businesses somebody deleted, so it opens on people.
 */
final class AuditTrail
{
    /** Changes that hand out or take away keys. */
    public const string ACCESS = 'access';

    /** What is worth seeing above the ordinary edit. */
    private const array STRONG = ['deleted', 'restored'];

    /**
     * The trail, newest first.
     *
     * @param  string  $causer  '' = anybody · 'system' = no person · an id
     * @param  string  $action  '' = any · otherwise the entry's `description`
     * @return Collection<int, Activity>
     */
    public static function rows(string $causer = '', bool $onlyStrong = false, int $limit = 200, string $action = ''): Collection
    {
        return Activity::query()
            ->with(['causer:id,name,email', 'subject'])
            ->when($causer === 'system', fn (Builder $query) => $query->whereNull('causer_id'))
            ->when($causer !== '' && $causer !== 'system', fn (Builder $query) => $query->where('causer_id', (int) $causer))
            ->when($action !== '', fn (Builder $query) => $query->where('description', $action))
            ->when($onlyStrong, fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->whereIn('description', self::STRONG)->orWhere('log_name', self::ACCESS),
            ))
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /** Every entry in the trail: `rows()` caps at 200 and filters, this is the whole. */
    public static function total(): int
    {
        return Activity::query()->count();
    }

    /**
     * The people who ever left a mark, plus the system.
     *
     * Built from the trail and not from the users table: whoever never did
     * anything would be an option that always comes back empty.
     *
     * @return array<string, string>
     */
    public static function causerOptions(): array
    {
        $ids = Activity::query()->whereNotNull('causer_id')->distinct()->pluck('causer_id');

        $people = User::withTrashed()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int $id): array => [(string) $id => $name])
            ->all();

        // `+`, not a spread: unpacking renumbers integer keys, and the key IS the user id.
        return ['system' => __('admin.audit.system')] + $people;
    }

    /**
     * The kinds of movement the trail holds, as the screen names them.
     *
     * Built from the trail, like the people: an action nobody ever took would
     * be an option that always comes back empty. One the screen has no words
     * for is left out rather than shown as a raw key.
     *
     * @return array<string, string>
     */
    public static function actionOptions(): array
    {
        return Activity::query()
            ->whereNotNull('description')
            ->distinct()
            ->pluck('description')
            ->filter(fn (string $description): bool => Lang::has('admin.audit.actions.'.$description))
            ->mapWithKeys(fn (string $description): array => [$description => __('admin.audit.actions.'.$description)])
            ->sort()
            ->all();
    }

    /** Whether this entry is one she should not have to look for. */
    public static function isStrong(Activity $entry): bool
    {
        return $entry->log_name === self::ACCESS
            || in_array((string) $entry->description, self::STRONG, true);
    }

    /**
     * What the entry happened TO, in words.
     *
     * The row survives its subject — that is the point of an audit — so a
     * deleted business still has to read as something, not as a blank.
     */
    public static function subjectOf(Activity $entry): string
    {
        $kind = __('admin.audit.subjects.'.class_basename((string) $entry->subject_type));
        $name = $entry->subject?->getAttribute('name');

        return $name === null ? $kind.' #'.$entry->subject_id : $kind.': '.$name;
    }
}
