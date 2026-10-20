<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Dto\RoleMenuNodeDto;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Who opens which door of the admin panel.
 *
 * The matrix is grouped by AREA because that is how a job is described — "she
 * works the claims and looks up a business" — and never by permission name.
 */
final class Access
{
    /** Opening the panel at all: every staff role carries it, so it is not a choice. */
    public const string PANEL = 'access-admin-panel';

    /**
     * Super-admin. It passes every gate through `Gate::before`, so it is not
     * editable from a screen and `AdminUserSeeder` is the only way in.
     */
    public const string OWNER = 'admin';

    /** Roles of the CLIENT panel: another audience, another screen. */
    private const array CLIENT_ROLES = ['client', 'agent'];

    /**
     * The staff roles, with how many people hold each.
     *
     * @return Collection<int, Role>
     */
    public static function staffRoles(): Collection
    {
        return Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', self::PANEL))
            ->whereNotIn('name', self::CLIENT_ROLES)
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderByRaw('case when name = ? then 0 else 1 end', [self::OWNER])
            ->orderBy('name')
            ->get();
    }

    /**
     * The permissions a role may be given, grouped by the area they open.
     *
     * `access-admin-panel` is not in it: every staff role has it by
     * definition, and a checkbox that cannot be unchecked is a lie.
     *
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        $areas = [];

        foreach (Permission::query()->orderBy('name')->pluck('name') as $name) {
            $area = self::areaOf($name);

            if ($area !== null) {
                $areas[$area][] = $name;
            }
        }

        // The order she reads the panel in, not the alphabet.
        $order = ['businesses', 'payments', 'support', 'moderation', 'testimonials', 'ai', 'adoption', 'catalog', 'company', 'integrations', 'settings', 'users', 'roles', 'logs'];

        uksort($areas, function (string $a, string $b) use ($order): int {
            $left = array_search($a, $order, true);
            $right = array_search($b, $order, true);

            return ($left === false ? PHP_INT_MAX : $left) <=> ($right === false ? PHP_INT_MAX : $right);
        });

        return $areas;
    }

    /**
     * The admin menu as the role editor paints it, read from `menus` so a new
     * screen shows up without touching the editor. A permission no menu row
     * owns lands in a last group: it must never become impossible to grant.
     *
     * @return list<RoleMenuNodeDto>
     */
    public static function menuTree(): array
    {
        $assignable = collect(self::matrix())->flatten()->all();

        $menuPermissions = Menu::unfilteredTree('admin')
            ->flatMap(fn (Menu $root): array => self::screensOf($root))
            ->pluck('permission')
            ->filter(fn (?string $permission): bool => in_array($permission, $assignable, true))
            ->unique();

        $inside = [];
        $orphans = [];

        foreach (array_diff($assignable, $menuPermissions->all()) as $permission) {
            $owner = $menuPermissions->first(fn (string $screen): bool => self::areaOf($screen) === self::areaOf($permission));

            if ($owner === null) {
                $orphans[] = $permission;
            } else {
                $inside[$owner][] = $permission;
            }
        }

        $first = [];
        $nodes = [];

        // A loop, not a map: an arrow function copies `$first`, and the walk needs the one list.
        foreach (Menu::unfilteredTree('admin') as $root) {
            $nodes[] = self::nodeOf($root, $assignable, $inside, $first);
        }

        if ($orphans !== []) {
            $nodes[] = new RoleMenuNodeDto(__('admin.roles.editor.other'), 'shield-check', null, null, $orphans, []);
        }

        return $nodes;
    }

    /**
     * What each screen permission takes with it when it is switched off: the
     * actions inside the screens it opens, across every row that shares it.
     *
     * @param  list<RoleMenuNodeDto>  $tree
     * @return array<string, list<string>>
     */
    public static function dependents(array $tree): array
    {
        $map = [];

        $walk = function (array $nodes) use (&$walk, &$map): void {
            foreach ($nodes as $node) {
                if ($node->permission !== null && $node->actions !== []) {
                    $map[$node->permission] = [...($map[$node->permission] ?? []), ...$node->actions];
                }

                $walk($node->children);
            }
        };

        $walk($tree);

        return $map;
    }

    /**
     * What a new role can start from: the templates and the staff roles that
     * already exist. Only permissions the matrix still offers survive, so a
     * stale template can never fail the save.
     *
     * @return array<string, array{label: string, permissions: list<string>}>
     */
    public static function startingPoints(): array
    {
        $assignable = collect(self::matrix())->flatten()->all();
        $keep = fn (iterable $permissions): array => array_values(array_intersect($assignable, [...$permissions]));
        $points = [];

        foreach (config('atendia.role_templates', []) as $key => $permissions) {
            $points['template:'.$key] = [
                'label' => __('admin.roles.editor.start_template', ['name' => __('admin.roles.editor.templates.'.$key)]),
                'permissions' => $keep($permissions),
            ];
        }

        foreach (self::staffRoles() as $role) {
            if (self::isProtected($role->name)) {
                continue;
            }

            $points['role:'.$role->name] = [
                'label' => __('admin.roles.editor.start_role', ['name' => $role->name]),
                'permissions' => $keep($role->permissions->pluck('name')),
            ];
        }

        return $points;
    }

    /**
     * The people holding a role, newest first, capped: a face or two says
     * "these are the ones this change reaches" better than a full list.
     *
     * @return Collection<int, User>
     */
    public static function holders(Role $role, int $limit = 5): Collection
    {
        return User::role($role->name)->orderBy('name')->limit($limit)->get();
    }

    /**
     * @return list<Menu>
     */
    private static function screensOf(Menu $item): array
    {
        $children = $item->childrenRecursive->filter(fn (Menu $child): bool => $child->is_active);

        return $children->isEmpty() ? [$item] : $children->flatMap(fn (Menu $child): array => self::screensOf($child))->all();
    }

    /**
     * @param  list<string>  $assignable
     * @param  array<string, list<string>>  $inside
     * @param  array<string, string>  $first  Which label opened each permission first, to name the rows that share it.
     */
    private static function nodeOf(Menu $item, array $assignable, array $inside, array &$first): RoleMenuNodeDto
    {
        $children = [];

        foreach ($item->childrenRecursive->filter(fn (Menu $child): bool => $child->is_active) as $child) {
            $children[] = self::nodeOf($child, $assignable, $inside, $first);
        }

        $permission = in_array($item->permission, $assignable, true) ? $item->permission : null;
        $sharedWith = null;

        if ($permission !== null && $children === []) {
            $sharedWith = $first[$permission] ?? null;
            $first[$permission] ??= $item->label;
        }

        $actions = $sharedWith === null && $permission !== null ? ($inside[$permission] ?? []) : [];

        return new RoleMenuNodeDto($item->label, $item->icon, $permission, $sharedWith, $actions, $children);
    }

    /**
     * One staff role by id, with what it opens and how many hold it.
     *
     * The protected one never comes back: a screen that could load it could
     * save it, and super-admin is not something a form may hand out.
     */
    public static function staffRole(int $id): ?Role
    {
        $role = Role::query()->with('permissions:id,name')->withCount('users')->find($id);

        return $role === null || self::isProtected($role->name) ? null : $role;
    }

    /** The same lookup by key, for the moment right after a role is saved. */
    public static function staffRoleNamed(string $name): ?Role
    {
        $id = Role::query()->where('name', $name)->value('id');

        return $id === null ? null : self::staffRole((int) $id);
    }

    /** Whether this role is the one no screen may touch. */
    public static function isProtected(string $role): bool
    {
        return $role === self::OWNER;
    }

    /**
     * The area a permission belongs to, or null when it is not the admin's.
     *
     * The client-panel keys live here too (one roles table for both panels),
     * and showing them on this matrix would repeat the mistake of mixing the
     * platform's own people with its clients.
     */
    private static function areaOf(string $permission): ?string
    {
        if (in_array($permission, [self::PANEL, 'access-client-app', 'manage-business'], true)) {
            return null;
        }

        return match (true) {
            in_array($permission, ['manage-admin-users', 'reset-two-factor'], true) => 'users',
            // The hub and its twelve masters are one area on screen: splitting
            // `catalogs` from `catalog` read as two unrelated things.
            str_starts_with($permission, 'catalog') => 'catalog',
            str_contains($permission, '.') => explode('.', $permission)[0],
            default => null,
        };
    }
}
