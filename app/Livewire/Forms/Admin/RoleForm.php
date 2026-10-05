<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Classes\Main\Access;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * A job described as the doors it opens.
 *
 * Every role saved here carries `access-admin-panel`: without it the role
 * would be a list of keys to a building nobody may enter.
 */
class RoleForm extends BaseForm
{
    public ?int $id = null;

    public string $name = '';

    /** @var list<string> The permissions ticked on the matrix. */
    public array $permissions = [];

    public function setup(?Role $role = null): void
    {
        $this->reset();

        if ($role === null) {
            return;
        }

        $this->id = $role->id;
        $this->name = $role->name;
        $this->permissions = $role->permissions->pluck('name')->all();
    }

    public function save(): NotificationDto
    {
        if ($this->id !== null && Access::isProtected((string) Role::query()->whereKey($this->id)->value('name'))) {
            return new NotificationDto(__('admin.roles.protected'), NotificationType::Error);
        }

        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated): NotificationDto {
            $role = Role::findOrCreate($validated['name'], 'web');

            // The panel key is implicit, never ticked: a staff role without it
            // is a set of keys to a building nobody may walk into.
            $role->syncPermissions([Access::PANEL, ...$validated['permissions']]);

            // Spatie caches every check; a role saved and not flushed keeps
            // letting somebody through a door she just closed.
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->setup();

            return new NotificationDto(__('admin.roles.saved', ['role' => $role->name]), NotificationType::Success);
        }, __('notifications.not_updated'));
    }

    protected function transformServiceData(): array
    {
        return [
            'name' => mb_strtolower(trim($this->name)),
            'permissions' => array_values(array_unique($this->permissions)),
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        $assignable = collect(Access::matrix())->flatten()->all();

        return [
            // A key, not a label: lowercase and no spaces, like the ones the
            // code already checks (`support`, `payments.view`).
            'name' => [
                'required', 'string', 'min:3', 'max:40', 'regex:/^[a-z][a-z0-9-]*$/',
                Rule::notIn([Access::OWNER, 'client', 'agent']),
                Rule::unique('roles', 'name')->ignore($this->id),
            ],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in($assignable)],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'name' => __('admin.roles.fields.name'),
            'permissions' => __('admin.roles.fields.permissions'),
        ];
    }
}
