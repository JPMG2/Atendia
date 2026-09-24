<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * A catalog item's name as a meaning vector, so "el tiroideo" in a chat
 * finds "Perfil tiroideo" on the shelf. A renamed item drops its stale
 * vector; the EmbedCatalog job fills the blanks.
 */
trait EmbedsName
{
    public static function bootEmbedsName(): void
    {
        static::saving(function (Model $item): void {
            if ($item->isDirty('name')) {
                $item->setAttribute('embedding', null);
            }
        });
    }

    public function initializeEmbedsName(): void
    {
        $this->mergeCasts(['embedding' => 'array']);
    }

    /**
     * The active item of the current tenant closest in meaning, or null.
     *
     * @param  list<float>  $vector
     * @return array{id: int, name: string, similarity: float}|null
     */
    public static function closestTo(array $vector): ?array
    {
        return static::closestMany($vector, 1)[0] ?? null;
    }

    /**
     * The current tenant's active items nearest in meaning, best first.
     *
     * @param  list<float>  $vector
     * @return list<array{id: int, name: string, similarity: float}>
     */
    public static function closestMany(array $vector, int $limit): array
    {
        return static::query()
            ->where('is_active', true)
            ->whereNotNull('embedding')
            ->select(['id', 'name'])
            ->selectVectorDistance('embedding', $vector, as: 'distance')
            ->orderByVectorDistance('embedding', $vector)
            ->limit($limit)
            ->get()
            ->map(fn (Model $item): array => [
                'id' => (int) $item->getKey(),
                'name' => (string) $item->getAttribute('name'),
                'similarity' => 1 - (float) $item->getAttribute('distance'),
            ])
            ->all();
    }
}
