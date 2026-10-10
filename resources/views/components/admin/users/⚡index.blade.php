<?php

use App\Actions\Account\SendEmailVerificationLink;
use App\Actions\Admin\RemindStaffTwoFactor;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Classes\Main\AuditTrail;
use App\Classes\Main\StaffTwoFactor;
use App\Livewire\Forms\Admin\AdminUserForm;
use App\Livewire\Forms\Admin\TwoFactorResetForm;
use App\Models\User;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
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

    /** Whether the second step is on: '' = everybody, on, off. */
    #[Url(as: 'doble', except: '')]
    public string $factor = '';

    public TwoFactorResetForm $resetForm;

    /** The person whose second step is about to be taken away; the panel asks for her password. */
    public ?int $resetting = null;

    /** The person whose trail is open in the side panel; locked, so only `openHistory()` (which authorizes) sets it. */
    #[Locked]
    public ?int $historyOf = null;

    /** How many entries the panel shows; the audit screen holds the rest. */
    private const int HISTORY_LIMIT = 30;

    public function mount(): void
    {
        $this->form->setup();
        $this->resetForm->setup();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'state', 'factor');
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function people(): Collection
    {
        return User::directory($this->search, $this->state, $this->factor);
    }

    /** @return array{on: int, total: int} */
    #[Computed]
    public function coverage(): array
    {
        return User::staffTwoFactorCoverage();
    }

    /** @return array<string, string> */
    #[Computed]
    public function factorOptions(): array
    {
        return ['on' => __('admin.users.factor.on'), 'off' => __('admin.users.factor.off')];
    }

    #[Computed]
    public function resettingPerson(): ?User
    {
        return $this->resetting === null ? null : User::staffMember($this->resetting);
    }

    public function startReset(int $id): void
    {
        // The lock is on the ACTION: the button is only drawn for who holds it.
        $this->authorize('reset-two-factor');

        $this->resetForm->setup();
        $this->resetting = $id;
    }

    public function cancelReset(): void
    {
        $this->resetForm->setup();
        $this->resetting = null;
    }

    public function confirmReset(): void
    {
        $this->authorize('reset-two-factor');

        if ($this->resetting === null) {
            return;
        }

        $this->dispatchNotification($this->resetForm->save($this->resetting));

        $this->resetting = null;
        unset($this->people, $this->coverage, $this->resettingPerson);
    }

    #[Computed]
    public function total(): int
    {
        return User::directoryTotal();
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

    public function openHistory(int $id): void
    {
        $this->authorize('audit.view');

        $this->historyOf = User::staffById($id)?->id;
    }

    public function closeHistory(): void
    {
        $this->historyOf = null;
    }

    #[Computed]
    public function historyPerson(): ?User
    {
        return $this->historyOf === null ? null : User::staffById($this->historyOf);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> */
    #[Computed]
    public function history(): \Illuminate\Database\Eloquent\Collection
    {
        return AuditTrail::rows((string) $this->historyOf, limit: self::HISTORY_LIMIT);
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

    public function remind(int $id): void
    {
        $this->authorize('manage-admin-users');

        $person = User::staffById($id);

        if ($person === null) {
            return;
        }

        $result = app(RemindStaffTwoFactor::class)->handle($person, auth()->user());

        $this->dispatchNotification(match ($result) {
            RemindStaffTwoFactor::SENT => new NotificationDto(
                __('admin.users.reminded', ['email' => $person->email, 'date' => (new StaffTwoFactor($person))->deadline?->format('d/m/Y')]),
                NotificationType::Success,
            ),
            RemindStaffTwoFactor::ALREADY => new NotificationDto(__('admin.users.reminded_already', ['email' => $person->email]), NotificationType::Info),
            default => new NotificationDto(__('admin.users.reminder_not_due'), NotificationType::Info),
        });
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
        <div class="flex items-center gap-4">
            <x-ui.result-count :shown="$this->people->count()" :total="$this->total" noun="admin.users.count" />

            @can('manage-admin-users')
                <x-ui.button variant="primary" icon="plus" wire:click="create">
                    {{ __('admin.users.new') }}
                </x-ui.button>
            @endcan
        </div>
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
                        span="text"
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

        {{-- Who holds the second step, and until when the rest owe it: said where she looks at the people. --}}
        <p class="bp-card-sub" data-testid="two-factor-coverage">
            {{ __('admin.users.coverage', ['on' => $this->coverage['on'], 'total' => $this->coverage['total']]) }}
            {{ (int) config('atendia.security.staff_two_factor.grace_days') > 0
                ? __('admin.users.coverage_grace', ['days' => (int) config('atendia.security.staff_two_factor.grace_days')])
                : __('admin.users.coverage_off') }}
        </p>

        @if ($resetting !== null && $this->resettingPerson !== null)
            <form wire:submit="confirmReset" class="aim-form" data-testid="two-factor-reset-form">
                <p class="text-strong text-sm font-semibold">{{ __('admin.users.reset.title', ['name' => $this->resettingPerson->name]) }}</p>
                <p class="bp-card-sub">{{ __('admin.users.reset.body') }}</p>

                <x-catalog.form-row>
                    <x-inputsform.input
                        span="long"
                        type="password"
                        name="current_password"
                        :label="__('admin.users.reset.password')"
                        autocomplete="current-password"
                        required
                        wire:model="resetForm.current_password"
                    />
                </x-catalog.form-row>

                <div class="aim-actions">
                    <x-ui.button type="submit" variant="primary" icon="shield-check">{{ __('admin.users.reset.accept') }}</x-ui.button>
                    <x-ui.button variant="danger" wire:click="cancelReset">{{ __('admin.users.cancel') }}</x-ui.button>
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
                data-key-focus="/"
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

            <x-inputsform.combobox
                size="s"
                span="short"
                :label="__('admin.users.two_factor')"
                name="factor"
                :options="$this->factorOptions"
                :value="$factor"
                :placeholder="__('admin.users.factor.all')"
                wire:model.live="factor"
            />

            @if ($search !== '' || $state !== '' || $factor !== '')
                <x-ui.button variant="ghost" size="sm" wire:click="clearFilters" data-key-click="c">
                    {{ __('admin.keys.clear_filters') }}
                </x-ui.button>
            @endif
        </x-catalog.form-row>

        @if ($this->people->isEmpty())
            <p class="text-muted text-sm">
                {{ $search === '' && $state === '' && $factor === '' ? __('admin.users.empty') : __('admin.users.no_match') }}
            </p>
        @else
            <div class="pay-table-wrap">
                <table class="pay-table" data-sortable>
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

                                <td class="is-name" data-label="{{ __('admin.users.two_factor') }}">
                                    @if ($person->two_factor_whatsapp_at !== null)
                                        <span class="status-tag is-brand">{{ __('admin.users.two_factor_on') }}</span>
                                    @else
                                        @php($owed = new StaffTwoFactor($person))
                                        {{-- Without it, what the platform asks of this person: a date, or the plazo already gone. --}}
                                        @if ($owed->overdue)
                                            <span class="status-tag is-danger">{{ __('admin.users.factor.overdue') }}</span>
                                        @elseif ($owed->deadline !== null && $person->accessState !== 'closed')
                                            <span class="status-tag is-warning">{{ __('admin.users.factor.until', ['date' => $owed->deadline->format('d/m')]) }}</span>
                                        @else
                                            —
                                        @endif
                                    @endif
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
                                  <div class="flex flex-wrap items-center justify-end gap-2">
                                    {{-- Opens beside the list: her question is what this person did,
                                    and asking it must not cost her the place she was in. --}}
                                    @can('audit.view')
                                        <x-ui.button size="sm" variant="ghost" icon="history" wire:click="openHistory({{ $person->id }})" data-testid="history-{{ $person->id }}">
                                            {{ __('admin.users.history') }}
                                        </x-ui.button>
                                    @endcan

                                    {{-- Only while the plazo still runs: past it the panel itself says it. --}}
                                    @php($running = $person->two_factor_whatsapp_at === null ? new StaffTwoFactor($person) : null)
                                    @if ($person->accessState === 'active' && $running?->deadline !== null && ! $running->overdue && ! $person->is(auth()->user()))
                                        @can('manage-admin-users')
                                            <x-ui.button size="sm" variant="secondary" icon="bell" wire:click="remind({{ $person->id }})" data-testid="remind-{{ $person->id }}">
                                                {{ __('admin.users.remind') }}
                                            </x-ui.button>
                                        @endcan
                                    @endif

                                    @if ($person->accessState === 'unverified')
                                        @can('manage-admin-users')
                                            <x-ui.button size="sm" variant="secondary" icon="mail" wire:click="resendVerification({{ $person->id }})">
                                                {{ __('admin.users.resend') }}
                                            </x-ui.button>
                                        @endcan
                                    @endif

                                    {{-- Only the owner, only on somebody else, only where there is something to take away. --}}
                                    @if ($person->two_factor_whatsapp_at !== null && ! $person->is(auth()->user()))
                                        @can('reset-two-factor')
                                            <x-ui.button size="sm" variant="ghost" icon="rotate-ccw" wire:click="startReset({{ $person->id }})" data-testid="two-factor-reset-{{ $person->id }}">
                                                {{ __('admin.users.reset.action') }}
                                            </x-ui.button>
                                        @endcan
                                    @endif
                                  </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <x-ui.key-hints>
        <kbd class="cmdk-kbd">/</kbd> {{ __('admin.keys.search') }}
        <kbd class="cmdk-kbd">c</kbd> {{ __('admin.keys.clear') }}
    </x-ui.key-hints>

    @if ($historyOf !== null && $this->historyPerson !== null)
        <x-ui.slide-over
            x-on:slide-over-close="$wire.closeHistory()"
            :title="__('admin.users.timeline.title', ['name' => $this->historyPerson->name])"
            :subtitle="$this->historyPerson->email"
        >
            @if ($this->history->isEmpty())
                <x-ui.empty-state icon="history" :title="__('admin.users.timeline.empty_title')" :body="__('admin.users.timeline.empty_body')" :compact="true" />
            @else
                <ol class="hist-list" data-testid="history-list">
                    @foreach ($this->history as $entry)
                        <li class="hist-item" wire:key="hist-{{ $entry->id }}">
                            <span class="hist-when font-mono">{{ $entry->created_at?->format('d/m/Y H:i') }}</span>
                            <span @class(['status-tag', 'is-danger' => AuditTrail::isStrong($entry), 'is-neutral' => ! AuditTrail::isStrong($entry)])>
                                {{ __('admin.audit.actions.'.$entry->description) }}
                            </span>
                            <span class="hist-on">{{ AuditTrail::subjectOf($entry) }}</span>
                        </li>
                    @endforeach
                </ol>

                @if ($this->history->count() >= 30)
                    <p class="bp-card-sub">{{ __('admin.users.timeline.capped', ['count' => 30]) }}</p>
                @endif
            @endif

            <x-slot:footer>
                <x-ui.button variant="secondary" icon="scroll-text" :href="route('admin.audit', ['quien' => $historyOf, 'fuertes' => 0])" wire:navigate>
                    {{ __('admin.users.timeline.open_audit') }}
                </x-ui.button>
            </x-slot:footer>
        </x-ui.slide-over>
    @endif
</div>
