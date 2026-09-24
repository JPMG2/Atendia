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
        $item = static::query()
            ->where('is_active', true)
            ->whereNotNull('embedding')
            ->select(['id', 'name'])
            ->selectVectorDistance('embedding', $vector, as: 'distance')
            ->orderByVectorDistance('embedding', $vector)
            ->first();

        return $item === null ? null : [
            'id' => (int) $item->id,
            'name' => (string) $item->name,
            'similarity' => 1 - (float) $item->getAttribute('distance'),
        ];
    }
}
