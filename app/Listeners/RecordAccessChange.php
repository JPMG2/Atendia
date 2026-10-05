<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Every change of access, written down.
 *
 * The rest of the audit rides `LogsActivity` on a model, which cannot see
 * this one: giving a role a permission writes a PIVOT row and fires no model
 * event. Without these four the trail says who edited a catalog but not who
 * handed somebody the keys — the one change that matters most.
 */
class RecordAccessChange
{
    public function handle(
        PermissionAttachedEvent|PermissionDetachedEvent|RoleAttachedEvent|RoleDetachedEvent $event,
    ): void {
        $granted = $event instanceof PermissionAttachedEvent || $event instanceof RoleAttachedEvent;
        $isRole = $event instanceof RoleAttachedEvent || $event instanceof RoleDetachedEvent;

        $names = $this->namesIn(
            $isRole ? $event->rolesOrIds : $event->permissionsOrIds,
            $isRole ? Role::class : Permission::class,
        );

        if ($names === []) {
            return;
        }

        // Writing it down must never break the change it records.
        rescue(fn () => activity('access')
            ->performedOn($event->model)
            ->causedBy(auth()->user())
            ->withProperties([
                'what' => $isRole ? 'role' : 'permission',
                'names' => $names,
            ])
            ->log($granted ? 'granted' : 'revoked'));
    }

    /**
     * The names behind what the event carries.
     *
     * The package passes ids from its own traits and models from elsewhere,
     * and its own docblock says a listener has to look before using them.
     *
     * @param  class-string<Model>  $model
     * @return list<string>
     */
    private function namesIn(mixed $subjects, string $model): array
    {
        // A single model must be WRAPPED, never cast: `(array) $model` hands
        // back its attributes, so revoking one permission reported three.
        $items = match (true) {
            $subjects instanceof Collection => $subjects->all(),
            $subjects instanceof Model => [$subjects],
            is_array($subjects) => $subjects,
            default => [$subjects],
        };

        $names = [];
        $ids = [];

        foreach ($items as $item) {
            if ($item instanceof Model) {
                $names[] = (string) $item->getAttribute('name');

                continue;
            }

            $ids[] = $item;
        }

        if ($ids !== []) {
            $names = [...$names, ...$model::query()->whereKey($ids)->pluck('name')->all()];
        }

        sort($names);

        return array_values(array_unique($names));
    }
}
