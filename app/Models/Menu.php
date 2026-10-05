<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MenuFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'panel',
        'permission',
        'label_key',
        'icon',
        'route_name',
        'badge',
        'placement',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Menu, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Children loaded recursively to arbitrary depth.
     *
     * @return HasMany<Menu, $this>
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    /**
     * @param  Builder<Menu>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Menu>  $query
     */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * The translated label, resolved from the i18n key.
     *
     * @return Attribute<string, never>
     */
    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => (string) __($this->label_key));
    }

    /**
     * The resolved URL, or null for group-only items without a route.
     *
     * @return Attribute<?string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->route_name ? route($this->route_name) : null);
    }

    public function hasChildren(): bool
    {
        return $this->childrenRecursive->isNotEmpty();
    }

    /**
     * Whether this item points to the current route.
     */
    public function isCurrent(): bool
    {
        return $this->route_name !== null && request()->routeIs($this->route_name);
    }

    /**
     * Whether any descendant points to the current route (to auto-open groups).
     */
    public function hasActiveDescendant(): bool
    {
        return $this->childrenRecursive->contains(
            fn (Menu $child): bool => $child->isCurrent() || $child->hasActiveDescendant()
        );
    }

    /**
     * Whether the item is visible: no permission means public inside the panel,
     * a permission means only whoever holds it — the admin passes through
     * Gate::before.
     */
    public function isVisibleTo(?Authenticatable $user): bool
    {
        return $this->permission === null
            || ($user !== null && $user->can($this->permission));
    }

    /**
     * The active menu tree for a panel: ordered roots with their recursive active
     * children, filtered by what the current user may see. Navigation's
     * #[Computed] memoises it per request; it is NOT cached across requests, as
     * serialising Eloquent to the store gives back __PHP_Incomplete_Class.
     *
     * @return Collection<int, Menu>
     */
    public static function tree(string $panel = 'client'): Collection
    {
        $roots = self::query()
            ->active()
            ->roots()
            ->where('panel', $panel)
            ->with('childrenRecursive')
            ->orderBy('sort_order')
            ->get();

        return self::filterByPermission($roots, auth()->user());
    }

    /**
     * The screen's own name, for a tab that has to say which screen it is.
     * The client panel's screens are nested components, so Livewire's
     * ->title() never reaches the head: the menu item names the tab, and a
     * card's deep link ("settings.correo") falls back to its parent screen.
     */
    public static function titleFor(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }

        $parent = Str::before($routeName, '.');

        $keys = self::query()
            ->whereIn('route_name', array_unique([$routeName, $parent]))
            ->pluck('label_key', 'route_name');

        $key = $keys[$routeName] ?? $keys[$parent] ?? null;

        return $key === null ? null : (string) __($key);
    }

    /**
     * Recursively drop items (and their subtrees) the user may not see.
     *
     * @param  Collection<int, Menu>  $items
     * @return Collection<int, Menu>
     */
    protected static function filterByPermission(Collection $items, ?Authenticatable $user): Collection
    {
        // Which ones are GROUPS, before the filter empties any of them.
        $groups = $items->filter(fn (Menu $item): bool => $item->childrenRecursive->isNotEmpty())->pluck('id')->all();

        return $items
            ->filter(fn (Menu $item): bool => $item->isVisibleTo($user))
            ->each(fn (Menu $item) => $item->setRelation(
                'childrenRecursive',
                self::filterByPermission($item->childrenRecursive, $user),
            ))
            // A group is a heading over its children and has no route of its
            // own: emptied by permissions it opened nothing, and "Plataforma"
            // stayed on the menu of a support person who could enter none of it.
            ->reject(fn (Menu $item): bool => $item->route_name === null
                && in_array($item->id, $groups, true)
                && $item->childrenRecursive->isEmpty())
            ->values();
    }
}
