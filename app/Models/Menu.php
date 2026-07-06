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
     * Whether the item is visible to the user: sin permiso = público dentro del
     * panel; con permiso = solo si el usuario lo tiene (el admin pasa por
     * Gate::before). Ver arquitectura de paneles.
     */
    public function isVisibleTo(?Authenticatable $user): bool
    {
        return $this->permission === null
            || ($user !== null && $user->can($this->permission));
    }

    /**
     * The active menu tree for a panel (admin|client): roots with recursive
     * active children, ordered, y filtrado por permiso para el usuario actual.
     * La memoización por request la da el #[Computed] de Navigation; NO se
     * cachea cross-request (serializar Eloquent al store da __PHP_Incomplete_Class).
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
     * Recursively drop items (and their subtrees) the user may not see.
     *
     * @param  Collection<int, Menu>  $items
     * @return Collection<int, Menu>
     */
    protected static function filterByPermission(Collection $items, ?Authenticatable $user): Collection
    {
        return $items
            ->filter(fn (Menu $item): bool => $item->isVisibleTo($user))
            ->each(fn (Menu $item) => $item->setRelation(
                'childrenRecursive',
                self::filterByPermission($item->childrenRecursive, $user),
            ))
            ->values();
    }
}
