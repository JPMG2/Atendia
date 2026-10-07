<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\DemoTag;
use App\Models\SeasonalWindow;
use Illuminate\Support\Facades\DB;

/**
 * Carries a season, and everything hanging off it, into next year.
 *
 * Rewriting Christmas by hand every December is how a season ends up with one
 * rubro missing and nobody noticing until it is live.
 */
class RepeatSeasonalWindow
{
    /** Null when next year's copy is already there: this never makes a second one. */
    public function handle(int $id): ?SeasonalWindow
    {
        $window = SeasonalWindow::query()->findOrFail($id);
        $name = $this->nextName($window);

        if (SeasonalWindow::query()->withTrashed()->where('name', $name)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($window, $name): SeasonalWindow {
            $copy = SeasonalWindow::query()->create([
                'name' => $name,
                'starts_at' => $window->starts_at?->addYear(),
                'ends_at' => $window->ends_at?->addYear(),
                'priority' => $window->priority,
                // Off, on purpose: a season a year away is written now and
                // turned on when it has been read, not the minute it is copied.
                'is_active' => false,
            ]);

            $window->demoTags()->get()->each(fn (DemoTag $tag) => DemoTag::query()->create([
                ...$tag->only(['slug', 'label', 'business_name', 'noun', 'chips', 'pool', 'sort_order']),
                'seasonal_window_id' => $copy->id,
                'is_active' => $tag->is_active,
            ]));

            return $copy;
        });
    }

    /**
     * The year in the name is replaced when it is there, and appended when it
     * is not: "Navidad 2026" must not become "Navidad 2026 2027".
     */
    private function nextName(SeasonalWindow $window): string
    {
        $year = (int) ($window->starts_at?->year ?? now()->year);
        $next = (string) ($year + 1);

        return str_contains($window->name, (string) $year)
            ? str_replace((string) $year, $next, $window->name)
            : $window->name.' '.$next;
    }
}
