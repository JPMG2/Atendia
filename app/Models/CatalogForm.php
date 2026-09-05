<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CatalogFormFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CatalogForm extends Model
{
    /** @use HasFactory<CatalogFormFactory> */
    use HasFactory;

    protected $fillable = [
        'group',
        'title',
        'description',
        'component',
        'icon',
        'order',
        'permission_key',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Active masters in rail order, keeping only what the user may open (a
     * null permission_key is public; super-admin passes via Gate::before).
     *
     * @return Collection<int, self>
     */
    public static function visibleTo(?User $user): Collection
    {
        return self::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->filter(fn (self $form): bool => $form->permission_key === null || (bool) $user?->can($form->permission_key))
            ->values();
    }
}
