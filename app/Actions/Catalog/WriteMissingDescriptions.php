<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Ai\Agents\CatalogCopywriter;
use App\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Fills in the descriptions a catalog is missing. ONE call for the whole batch,
 * never one per item: the model writes a list as cheaply as it writes a line,
 * and a loop of calls is the same work at ten times the price.
 */
class WriteMissingDescriptions
{
    /** A batch the model can hold and the owner can still review in one sitting. */
    private const int BATCH = 25;

    /**
     * @param  Collection<int, Model>  $items  Services or products of this business.
     * @return int how many were written
     */
    public function handle(Business $business, Collection $items): int
    {
        // Filtered in PHP: an item that already reads well never reaches the model.
        $pending = $items
            ->filter(fn (Model $item): bool => trim((string) $item->getAttribute('description')) === '')
            ->take(self::BATCH);

        if ($pending->isEmpty()) {
            return 0;
        }

        try {
            $response = new CatalogCopywriter()->prompt($this->ask($business, $pending));
        } catch (Throwable $e) {
            report($e);

            return 0;
        }

        return $this->apply($pending, $response['items'] ?? []);
    }

    /**
     * @param  Collection<int, Model>  $pending
     */
    private function ask(Business $business, Collection $pending): string
    {
        $trade = $business->activities()->pluck('name')->implode(', ');
        $names = $pending->map(fn (Model $item): string => '- '.$item->getAttribute('name'))->implode("\n");

        return "Negocio: {$business->name}".($trade !== '' ? " (rubro: {$trade})" : '')."\n\nNombres:\n{$names}";
    }

    /**
     * Matched by name, not by order: the model is asked to echo the name back
     * precisely so a reordered answer cannot describe the wrong item.
     *
     * @param  Collection<int, Model>  $pending
     * @param  array<int, array{name?: string, description?: string}>  $written
     */
    private function apply(Collection $pending, array $written): int
    {
        $byName = collect($written)
            ->filter(fn (array $row): bool => trim((string) ($row['name'] ?? '')) !== '')
            ->keyBy(fn (array $row): string => trim((string) $row['name']));

        $filled = 0;

        foreach ($pending as $item) {
            $description = trim((string) ($byName[$item->getAttribute('name')]['description'] ?? ''));

            if ($description === '') {
                continue;
            }

            $item->forceFill(['description' => mb_substr($description, 0, 500)])->save();
            $filled++;
        }

        return $filled;
    }
}
