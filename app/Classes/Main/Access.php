<?php

declare(strict_types=1);

namespace App\Classes\Main;

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
