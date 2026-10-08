<?php

use App\Actions\Account\SendEmailVerificationLink;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\Admin\AdminUserForm;
use App\Models\User;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The platform's OWN people: whoever may open the admin panel.
 *
 * A business owner is a CLIENT and lives in Negocios. Listing them here read
 * as if they were staff, which is a screen lying about who holds the keys.
 */
new class extends Component
{
    use HasNotifications;

    public AdminUserForm $form;

    /** Whether the create form is open; it sits above the list. */
    public bool $creating = false;

    #[Url(as: 'buscar', except: '')]
    public string $search = '';

    #[Url(as: 'estado', except: '')]
    public string $state = '';

    public function mount(): void
    {
        $this->form->setup();
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function people(): Collection
    {
        return User::directory($this->search, $this->state);
    }

    /** @return array<string, string> */
    #[Computed]
    public function stateOptions(): array
    {
        return User::accessStates();
    }

    /** @return array<string, string> */
    #[Computed]
    public function roleOptions(): array
    {
        return AdminUserForm::assignableRoles();
    }

    /** @return list<string> */
    #[Computed]
    public function panelRoles(): array
    {
        return User::panelRoleNames();
    }

    public function create(): void
    {
        $this->form->setup();
        $this->creating = true;
    }

    public function cancel(): void
    {
        $this->form->setup();
        $this->creating = false;
    }

    public function store(): void
    {
        // The lock is on the ACTION, never on hiding the button: a key to the
        // panel is the one thing a hidden control must not be able to grant.
        $this->authorize('manage-admin-users');

        $this->dispatchNotification($this->form->save());

        $this->creating = false;
        unset($this->people);
    }

    /** The commonest blocker, resolved where it was found. */
    public function resendVerification(int $id): void
    {
        $this->authorize('manage-admin-users');

        $user = User::staffAwaitingVerification($id);

        if ($user === null) {
            return;
        }

        app(SendEmailVerificationLink::class)->handle($user);

        $this->dispatchNotification(
            new NotificationDto(__('admin.users.verification_sent', ['email' => $user->email]), NotificationType::Success),
        );
    }

    /** The tab is copy: a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('admin.users.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('admin.users.title')" :sub="__('admin.users.sub')">
        @can('manage-admin-users')
            <x-ui.button variant="primary" icon="plus" wire:click="create">
                {{ __('admin.users.new') }}
            </x-ui.button>
        @endcan
    </x-ui.page-head>

    <x-ui.card class="p-5">
        @if ($creating)
            <form wire:submit="store" class="aim-form">
                <x-catalog.form-row>
                    <x-inputsform.input
                        span="text"
                        :label="__('admin.users.fields.name')"
                        required
                        name="name"
                        maxlength="255"
                        wire:model="form.name"
                    />

                    <x-inputsform.input
                        span="long"
                        :label="__('admin.users.fields.email')"
                        required
                        name="email"
                        type="email"
                        :hint="__('admin.users.fields.email_hint')"
                        maxlength="255"
                        wire:model="form.email"
                    />

                    <x-inputsform.combobox
                        span="short"
                        :label="__('admin.users.fields.role')"
                        required
                        name="role"
                        :options="$this->roleOptions"
                        :value="$form->role"
                        wire:model="form.role"
                    />
                </x-catalog.form-row>

                <div class="aim-actions">
                    <x-ui.button type="submit" variant="primary" icon="check">{{ __('admin.users.save') }}</x-ui.button>
                    <x-ui.button variant="danger" wire:click="cancel">{{ __('admin.users.cancel') }}</x-ui.button>
                    <span class="sup-age">{{ __('admin.users.no_password') }}</span>
                </div>
            </form>
        @endif

        <x-catalog.form-row>
            <x-inputsform.input
                size="s"
                span="text"
                :label="__('admin.users.search')"
                name="search"
                icon="search"
                :placeholder="__('admin.users.search_placeholder')"
                wire:model.live.debounce.400ms="search"
            />

            <x-inputsform.combobox
                size="s"
                span="short"
                :label="__('admin.users.state')"
                name="state"
                :options="$this->stateOptions"
                :value="$state"
                :placeholder="__('admin.users.all_states')"
                wire:model.live="state"
            />
        </x-catalog.form-row>

        @if ($this->people->isEmpty())
            <p class="text-muted text-sm">
                {{ $search === '' && $state === '' ? __('admin.users.empty') : __('admin.users.no_match') }}
            </p>
        @else
            <div class="pay-table-wrap">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.users.person') }}</th>
                            <th>{{ __('admin.users.role') }}</th>
                            <th>{{ __('admin.users.state') }}</th>
                            <th>{{ __('admin.users.two_factor') }}</th>
                            <th>{{ __('admin.users.last_login') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->people as $person)
                            <tr wire:key="user-{{ $person->id }}">
                                <td class="is-name is-key" data-label="{{ __('admin.users.person') }}">
                                    <span class="aiu-name">{{ $person->name }}</span>
                                    <span class="aiu-note font-mono">{{ $person->email }}</span>
                                </td>

                                <td data-label="{{ __('admin.users.role') }}">
                                    {{-- Only the roles that open THIS panel: nearly everybody
                                    also holds `client`, which says nothing here. --}}
                                    @foreach ($person->roles->whereIn('name', $this->panelRoles) as $role)
                                        <span @class(['status-tag', 'is-brand' => $role->name === 'admin', 'is-neutral' => $role->name !== 'admin'])>
                                            {{ App\Models\User::roleLabel($role->name) }}
                                        </span>
                                    @endforeach
                                </td>

                                <td data-label="{{ __('admin.users.state') }}">
                                    <span @class([
                                        'status-tag',
                                        'is-neutral' => $person->accessState === 'active',
                                        'is-warning' => $person->accessState === 'unverified',
                                        'is-danger' => $person->accessState === 'closed',
                                    ])>{{ __('admin.users.states.'.$person->accessState) }}</span>
                                </td>

                                <td data-label="{{ __('admin.users.two_factor') }}">
                                    {{ $person->two_factor_whatsapp_at !== null ? __('admin.users.two_factor_on') : '—' }}
                                </td>

                                <td class="is-name" data-label="{{ __('admin.users.last_login') }}">
                                    @if ($person->latestLogin === null)
                                        {{-- Never is not "a long time ago": a key nobody ever
                                        used is a key to take back. --}}
                                        <span class="text-muted">{{ __('admin.users.never') }}</span>
                                    @else
                                        <span class="font-mono">{{ $person->latestLogin->created_at->format('d/m/Y H:i') }}</span>
                                        @if ($person->latestLogin->location)
                                            {{-- Where from turns a date into a security signal. --}}
                                            <span class="aiu-note">{{ $person->latestLogin->location }}</span>
                                        @endif
                                    @endif
                                </td>

                                <td data-label="">
                                    @if ($person->accessState === 'unverified')
                                        @can('manage-admin-users')
                                            <x-ui.button size="sm" variant="secondary" icon="mail" wire:click="resendVerification({{ $person->id }})">
                                                {{ __('admin.users.resend') }}
                                            </x-ui.button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
</div>
