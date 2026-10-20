<?php

use App\Classes\Main\Access;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Which doors each job opens.
 *
 * The matrix is where a role is born, so the panel can grow with the people
 * it will need — claims, design, call centre — without touching a seeder.
 */
new class extends Component
{
    use HasNotifications;

    /** @return Collection<int, Role> */
    #[Computed]
    public function roles(): Collection
    {
        return Access::staffRoles();
    }

    /** A role nobody holds can go; one in use would lock people out. */
    public function destroy(int $id): void
    {
        $this->authorize('roles.manage');

        $role = Access::staffRole($id);

        if ($role === null) {
            return;
        }

        if ($role->users_count > 0) {
            $this->dispatchNotification(
                new NotificationDto(__('admin.roles.in_use', ['count' => $role->users_count]), NotificationType::Error),
            );

            return;
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->dispatchNotification(
            new NotificationDto(__('admin.roles.deleted', ['role' => $role->name]), NotificationType::Success),
        );

        unset($this->roles);
    }

    /** The tab is copy: a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('admin.roles.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('admin.roles.title')" :sub="__('admin.roles.sub')">
        <x-ui.button variant="primary" icon="plus" :href="route('admin.roles.create')" wire:navigate>
            {{ __('admin.roles.new') }}
        </x-ui.button>
    </x-ui.page-head>

    <x-ui.card class="p-5">
        <div class="pay-table-wrap">
            <table class="pay-table" data-sortable>
                <thead>
                    <tr>
                        <th>{{ __('admin.roles.columns.role') }}</th>
                        <th class="is-num">{{ __('admin.roles.columns.people') }}</th>
                        <th>{{ __('admin.roles.columns.opens') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->roles as $role)
                        <tr wire:key="role-{{ $role->id }}">
                            <td class="is-name is-key" data-label="{{ __('admin.roles.columns.role') }}">
                                <span class="aiu-name">
                                    {{ \App\Models\User::roleLabel($role->name) }}
                                    @if (App\Classes\Main\Access::isProtected($role->name))
                                        <span class="status-tag is-brand">{{ __('admin.roles.protected_tag') }}</span>
                                    @endif
                                </span>
                                <span class="aiu-note font-mono">{{ $role->name }}</span>
                            </td>

                            <td class="is-num font-mono" data-label="{{ __('admin.roles.columns.people') }}">
                                @if ($role->users_count > 0 && auth()->user()->can('users.view'))
                                    <a class="row-link" href="{{ route('admin.users', ['rol' => $role->name]) }}" wire:navigate>{{ $role->users_count }}</a>
                                @else
                                    {{ $role->users_count }}
                                @endif
                            </td>

                            <td data-label="{{ __('admin.roles.columns.opens') }}">
                                @if (App\Classes\Main\Access::isProtected($role->name))
                                    {{-- Saying "17 areas" of super-admin would be a lie the
                                    day an area is added: it opens whatever exists. --}}
                                    <span class="text-muted text-sm">{{ __('admin.roles.opens_everything') }}</span>
                                @else
                                    @php($opens = $role->permissions->count() - 1)
                                    <span class="aiu-note">{{ trans_choice('admin.roles.opens_count', $opens, ['count' => $opens]) }}</span>
                                @endif
                            </td>

                            <td data-label="">
                                @unless (App\Classes\Main\Access::isProtected($role->name))
                                    <div class="flex flex-wrap gap-2">
                                        <x-ui.button size="sm" variant="secondary" icon="pencil" :href="route('admin.roles.edit', $role->id)" wire:navigate data-row-action>
                                            {{ __('admin.roles.edit') }}
                                        </x-ui.button>

                                        @if ($role->users_count === 0)
                                            <x-ui.button
                                                size="sm"
                                                variant="danger"
                                                icon="trash-2"
                                                x-on:click="dialog.confirm({
                                                    title: {{ Illuminate\Support\Js::from(__('admin.roles.delete_title')) }},
                                                    message: {{ Illuminate\Support\Js::from(__('admin.roles.delete_body', ['role' => $role->name])) }},
                                                    accept: {{ Illuminate\Support\Js::from(__('admin.roles.delete_accept')) }},
                                                    type: 'danger',
                                                }).then(ok => ok && $wire.destroy({{ $role->id }}))"
                                            >{{ __('admin.roles.delete') }}</x-ui.button>
                                        @endif
                                    </div>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <x-ui.key-hints />
</div>
