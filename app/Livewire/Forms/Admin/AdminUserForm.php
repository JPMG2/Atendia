<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Actions\Account\SendEmailVerificationLink;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Giving somebody a key to the admin panel.
 *
 * The `admin` role is NOT offered: it passes every gate through
 * `Gate::before`, so handing it out from a screen would turn one stolen
 * session into the whole platform. That one stays seeder-only, keyed to
 * ADMIN_EMAIL, and this form can only grant the panel itself.
 */
class AdminUserForm extends BaseForm
{
    public string $name = '';

    public string $email = '';

    public string $role = 'support';

    public function setup(): void
    {
        $this->reset();
    }

    /**
     * The roles this screen may hand out: whatever opens the admin panel,
     * minus super-admin. Read from the permission and not from a list, so a
     * role created tomorrow joins without anybody remembering this file.
     *
     * @return array<string, string>
     */
    public static function assignableRoles(): array
    {
        return Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', 'access-admin-panel'))
            ->where('name', '!=', 'admin')
            ->orderBy('name')
            ->pluck('name')
            ->mapWithKeys(fn (string $name): array => [$name => __('admin.users.roles.'.$name)])
            ->all();
    }

    public function save(): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($validated): NotificationDto {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                // Never a password chosen by somebody else: the account is
                // claimed through the verification link and its own reset.
                'password' => Hash::make(Str::password(32)),
            ]);

            $user->assignRole($validated['role']);

            app(SendEmailVerificationLink::class)->handle($user);

            $this->setup();

            return new NotificationDto(__('admin.users.created', ['email' => $user->email]), NotificationType::Success);
        }, __('notifications.not_created'));
    }

    protected function transformServiceData(): array
    {
        return [
            'name' => trim($this->name),
            'email' => mb_strtolower(trim($this->email)),
            'role' => $this->role,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            // No `withoutTrashed`: a closed account keeps its address reserved
            // for its own restore, so it still counts as taken.
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', 'string', Rule::in(array_keys(self::assignableRoles()))],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'name' => __('admin.users.fields.name'),
            'email' => __('admin.users.fields.email'),
            'role' => __('admin.users.fields.role'),
        ];
    }
}
