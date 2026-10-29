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
     * The client menu as the mobile app draws it: the web's own tree, in the
     * web's own order, cut down to the screens the app already has. A button
     * for a screen that does not exist yet is not drawn; the list in
     * `atendia.app.screens` grows with each wave.
     *
     * @return Collection<int, Menu>
     */
    public static function forApp(): Collection
    {
        return self::onlyScreens(self::tree('client'), config('atendia.app.screens'));
    }

    /**
     * The admin menu as the app draws it for the platform's staff: the web's
     * own tree and order, cut to the admin screens the app already has and to
     * what this person may open.
     *
     * @return Collection<int, Menu>
     */
    public static function forAdminApp(): Collection
    {
        return self::onlyScreens(self::withAdminBadges(self::tree('admin')), config('atendia.app.admin_screens'));
    }

    /**
     * The admin's live counts laid over the tree: what waits on her call, on
     * its item and on the group above it (a closed branch must not hide it).
     * One place, so the web's sidebar and the app's bar show the same number.
     *
     * @param  Collection<int, Menu>  $tree
     * @return Collection<int, Menu>
     */
    public static function withAdminBadges(Collection $tree): Collection
    {
        $counts = [
            'admin.moderation' => fn (): int => ModerationFlag::pendingCount(),
            'admin.support' => fn (): int => SupportTicket::awaitingReplyCount(),
        ];

        foreach ($counts as $route => $count) {
            if (self::holds($tree, $route)) {
                self::overlayBadge($tree, $route, $count());
            }
        }

        return $tree;
    }

    /** @param  Collection<int, Menu>  $items */
    private static function holds(Collection $items, string $route): bool
    {
        return $items->contains(fn (Menu $item): bool => $item->route_name === $route || self::holds($item->childrenRecursive ?? new Collection, $route));
    }

    /** @param  Collection<int, Menu>  $items */
    private static function overlayBadge(Collection $items, string $route, int $count, ?Menu $parent = null): void
    {
        foreach ($items as $item) {
            if ($item->route_name === $route && $count > 0) {
                $item->badge = (string) $count;
                $parent?->setAttribute('badge', (string) $count);
            }

            self::overlayBadge($item->childrenRecursive ?? new Collection, $route, $count, $item);
        }
    }

    /**
     * @param  Collection<int, Menu>  $items
     * @param  list<string>  $screens
     * @return Collection<int, Menu>
     */
    private static function onlyScreens(Collection $items, array $screens): Collection
    {
        return $items
            ->each(fn (Menu $item) => $item->setRelation(
                'childrenRecursive',
                self::onlyScreens($item->childrenRecursive, $screens),
            ))
            ->filter(fn (Menu $item): bool => in_array($item->route_name, $screens, true) || $item->childrenRecursive->isNotEmpty())
            ->values();
    }

    /**
     * Every active root of a panel with its descendants, whoever is looking.
     * For the screens that EDIT access: filtering by the viewer's permissions
     * there would hide exactly the doors being handed out.
     *
     * @return Collection<int, Menu>
     */
    public static function unfilteredTree(string $panel): Collection
    {
        return self::query()
            ->active()
            ->roots()
            ->where('panel', $panel)
            ->with('childrenRecursive')
            ->orderBy('sort_order')
            ->get();
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
     * The group each menu item sits under, by label key: how a screen is named
     * in full ("Negocios › Todos") when its own label alone says nothing.
     *
     * @return array<string, string> child label key => parent label key
     */
    public static function parentLabelKeys(): array
    {
        return self::query()
            ->whereNotNull('parent_id')
            ->with('parent:id,label_key')
            ->get(['id', 'parent_id', 'label_key'])
            ->mapWithKeys(fn (self $item): array => [$item->label_key => (string) $item->parent?->label_key])
            ->filter()
            ->all();
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
