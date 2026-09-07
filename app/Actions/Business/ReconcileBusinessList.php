<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared reconciliation for the business's named lists: dropped names leave
 * softly, a trashed one coming back is restored (unique owner+name outlives
 * a soft delete), the rest upserts through the owner. With `$known` only
 * names the screen showed may be dropped; null keeps the full reconciliation
 * for a list with a single writer.
 */
abstract class ReconcileBusinessList
{
    /** The Business relation the list lives on. */
    abstract protected function relation(): string;

    /** Hook for a subclass to enrich a row before it is saved. */
    protected function decorate(Model $row): void {}

    /**
     * Says whether the LIST changed: the parent row never does, and judging
     * by its `wasChanged()` reported "nothing changed" with fresh rows in
     * plain sight — caught live on 2026-09-06.
     *
     * @param  list<string>  $names  Already normalized and validated by the calling form.
     * @param  list<string>|null  $known
     */
    public function handle(Business $business, array $names, ?array $known = null): bool
    {
        $relation = $this->relation();

        $doomed = ($known === null
            ? $business->{$relation}()->whereNotIn('name', $names)
            : $business->{$relation}()->whereIn('name', array_diff($known, $names)))->get();

        $doomed->each->delete();

        $changed = $doomed->isNotEmpty();

        foreach ($names as $name) {
            $row = $business->{$relation}()->withTrashed()->firstOrNew(['name' => $name]);

            if ($row->trashed()) {
                $row->restore();
            }

            $this->decorate($row);

            $row->save();

            $changed = $changed || $row->wasRecentlyCreated || $row->wasChanged();
        }

        return $changed;
    }
}
