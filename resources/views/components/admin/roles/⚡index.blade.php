<?php

use App\Classes\Main\Access;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\Admin\RoleForm;
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

    public RoleForm $form;

    /** Which role is open on the matrix; null means nobody is being edited. */
    public ?int $editing = null;

    /** @return Collection<int, Role> */
    #[Computed]
    public function roles(): Collection
    {
        return Access::staffRoles();
    }

    /** @return array<string, list<string>> */
    #[Computed]
    public function matrix(): array
    {
        return Access::matrix();
    }

    public function create(): void
    {
        $this->form->setup();
        $this->editing = 0;
    }

    public function edit(int $id): void
    {
        // The owner's role never comes back from there: it passes every gate,
        // so a screen that could load it would be the way to lose the platform.
        $role = Access::staffRole($id);

        if ($role === null) {
            return;
        }

        $this->form->setup($role);
        $this->editing = $id;
    }

    public function cancel(): void
    {
        $this->form->setup();
        $this->editing = null;
    }

    public function save(): void
    {
        $this->authorize('roles.manage');

        $this->dispatchNotification($this->form->save());

        $this->editing = null;
        unset($this->roles);
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
        <x-ui.button variant="primary" icon="plus" wire:click="create">
            {{ __('admin.roles.new') }}
        </x-ui.button>
    </x-ui.page-head>

    @if ($editing !== null)
        <x-ui.card class="mb-3 p-5">
            <form wire:submit="save">
                <x-catalog.form-row>
                    <x-inputsform.input
                        span="short"
                        :label="__('admin.roles.fields.name')"
                        required
                        name="name"
                        :hint="__('admin.roles.fields.name_hint')"
                        maxlength="40"
                        wire:model="form.name"
                    />
                </x-catalog.form-row>

                {{-- Grouped by area because that is how a job is described:
                "works the claims and looks up a business", never by key. --}}
                @foreach ($this->matrix as $area => $permissions)
                    <div class="role-area" wire:key="area-{{ $area }}">
                        <h3 class="aiu-section">{{ __('admin.roles.areas.'.$area) }}</h3>

                        <div class="role-grid">
                            @foreach ($permissions as $permission)
                                <x-ui.checkbox
                                    :name="'permissions-'.$permission"
                                    :label="__('admin.roles.permissions.'.$permission)"
                                    :value="$permission"
                                    :checked="in_array($permission, $form->permissions, true)"
                                    wire:model="form.permissions"
                                />
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="aim-actions">
                    <x-ui.button type="submit" variant="primary" icon="check">{{ __('admin.roles.save') }}</x-ui.button>
                    <x-ui.button variant="danger" wire:click="cancel">{{ __('admin.roles.cancel') }}</x-ui.button>
                    <span class="sup-age">{{ __('admin.roles.panel_implicit') }}</span>
                </div>
            </form>
        </x-ui.card>
    @endif

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
                                {{ $role->users_count }}
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
                                        <x-ui.button size="sm" variant="secondary" icon="pencil" wire:click="edit({{ $role->id }})" data-row-action>
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
</div>
