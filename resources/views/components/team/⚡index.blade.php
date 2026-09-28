<?php

use App\Classes\Main\Client;
use App\Classes\Main\Plan;
use App\Classes\Main\Team;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\Team\DepartmentForm;
use App\Livewire\Forms\Team\TeamMemberForm;
use App\Models\Country;
use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Equipo": who works next to the assistant and the departments a handoff
 * lands in. Owner-only by its route; every write goes through the client's
 * Team piece, pinned to this business.
 */
new class extends Component
{
    use HasNotifications;

    public TeamMemberForm $member;

    public DepartmentForm $department;

    public bool $memberOpen = false;

    public bool $departmentOpen = false;

    public function mount(): void
    {
        $this->member->setup();
        $this->department->setup();
    }

    #[Computed]
    public function team(): ?Team
    {
        return Client::for(Auth::user())->team;
    }

    /** @return list<array{code: string, flag: string}> */
    #[Computed]
    public function phoneCountries(): array
    {
        return Country::phoneFlags(states: [true]);
    }

    #[Computed]
    public function defaultDial(): ?string
    {
        return Country::dialCode(Auth::user()?->business?->country_id);
    }

    public function openMember(?int $userId = null): void
    {
        $this->member->setup($userId);
        $this->departmentOpen = false;
        $this->memberOpen = true;
    }

    public function saveMember(): void
    {
        $notification = $this->member->save();
        $this->dispatchNotification($notification);

        if ($notification->type === NotificationType::Success) {
            $this->memberOpen = false;
            unset($this->team);
        }
    }

    public function openDepartment(?int $departmentId = null): void
    {
        $this->department->setup($departmentId);
        $this->memberOpen = false;
        $this->departmentOpen = true;
    }

    public function saveDepartment(): void
    {
        $notification = $this->department->save();
        $this->dispatchNotification($notification);

        if ($notification->type === NotificationType::Success) {
            $this->departmentOpen = false;
            unset($this->team);
        }
    }

    public function deleteDepartment(int $departmentId): void
    {
        $this->team?->deleteDepartment($departmentId);
        $this->departmentOpen = false;
        unset($this->team);
        $this->dispatchNotification(new NotificationDto(__('team.notify.department_deleted'), NotificationType::Success));
    }

    public function resend(int $invitationId): void
    {
        $this->team?->resend($invitationId, Auth::user());
        unset($this->team);
        $this->dispatchNotification(new NotificationDto(__('team.notify.resent'), NotificationType::Success));
    }

    public function cancelInvitation(int $invitationId): void
    {
        $this->team?->cancel($invitationId);
        unset($this->team);
        $this->dispatchNotification(new NotificationDto(__('team.notify.cancelled'), NotificationType::Success));
    }

    public function remove(int $userId): void
    {
        $this->team?->remove($userId);
        unset($this->team);
        $this->dispatchNotification(new NotificationDto(__('team.notify.removed'), NotificationType::Success));
    }

    public function render(): View
    {
        return $this->view()->title(__('team.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('team.title')" :sub="__('team.sub')" />

    @php($team = $this->team)

    <div class="bp-layout">
        <div class="bp-main">
            <x-ui.card class="bp-card">
                <div class="bp-card-head">
                    <h2>{{ __('team.people.title') }}</h2>
                    <span class="bp-chip font-mono">{{ $team->members->count() + $team->invitations->count() }}</span>
                    <x-ui.button class="ml-auto" size="sm" icon="user-plus" wire:click="openMember">
                        {{ __('team.people.invite') }}
                    </x-ui.button>
                </div>
                <p class="bp-card-sub">{{ __('team.people.sub') }}</p>

                <ul class="tm-list">
                    @foreach ($team->members as $person)
                        @php($isYou = $person->id === auth()->id())
                        <li class="tm-row" wire:key="person-{{ $person->id }}">
                            <x-ui.avatar :name="$person->name" :src="$person->avatarUrl()" size="md" />

                            <div class="min-w-0 flex-1">
                                <p class="text-strong flex flex-wrap items-center gap-2 text-sm font-semibold">
                                    <span>{{ $person->name }}</span>
                                    @if ($isYou)
                                        <span class="text-muted text-xs font-normal">({{ __('team.people.you') }})</span>
                                    @endif
                                    <span @class(['tm-role', 'is-owner' => ! $person->isAgent()])>{{ __('team.roles.'.($person->isAgent() ? 'agent' : 'owner')) }}</span>
                                    <span @class(['tm-presence', 'is-away' => ! $person->is_available])>
                                        {{ $person->is_available ? __('team.people.available') : __('team.people.away') }}
                                    </span>
                                </p>
                                <p class="text-muted text-xs">
                                    {{-- Each datum wraps whole: an email or a phone split mid-way reads as two. --}}
                                    <span class="inline-block">{{ $person->email }}</span>
                                    @if ($person->whatsapp !== null)
                                        <span class="inline-block whitespace-nowrap">· <span class="font-mono">+{{ $person->whatsapp }}</span></span>
                                    @else
                                        <span>· {{ __('team.people.no_whatsapp') }}</span>
                                    @endif
                                </p>
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    {{-- Loops say $room: $department is the sheet's Form, and a loop variable would shadow it. --}}
                                    @forelse ($person->departments as $room)
                                        <span class="tm-dept" wire:key="pd-{{ $person->id }}-{{ $room->id }}">{{ $room->name }}</span>
                                    @empty
                                        <span class="text-muted text-xs">{{ __('team.people.all_departments') }}</span>
                                    @endforelse
                                </div>
                            </div>

                            @if ($person->isAgent())
                                <div class="flex flex-none items-center gap-1">
                                    <x-ui.icon-button icon="pencil" size="sm" variant="ghost" :label="__('team.people.edit')" wire:click="openMember({{ $person->id }})" />
                                    {{-- Removing someone never strands a customer: the message names where their open threads go. --}}
                                    <x-ui.icon-button
                                        icon="trash-2"
                                        size="sm"
                                        variant="ghost"
                                        :label="__('team.people.remove')"
                                        x-data
                                        x-on:click="dialog.confirm({
                                            title: {{ Js::from(__('team.people.remove_title', ['name' => $person->name])) }},
                                            message: {{ Js::from($person->open_threads > 0 ? __('team.people.remove_message', ['count' => $person->open_threads]) : __('team.people.remove_message_none')) }},
                                            accept: {{ Js::from(__('team.people.remove_accept')) }},
                                            type: 'danger',
                                        }).then((yes) => yes && $wire.remove({{ $person->id }}))"
                                    />
                                </div>
                            @endif
                        </li>
                    @endforeach

                    @foreach ($team->invitations as $invitation)
                        <li class="tm-row" wire:key="invitation-{{ $invitation->id }}">
                            <span class="tm-pending-avatar"><x-icon name="mail" :size="18" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-strong flex flex-wrap items-center gap-2 text-sm font-semibold">
                                    <span>{{ $invitation->name ?? $invitation->email }}</span>
                                    <span class="tm-role">{{ __('team.roles.agent') }}</span>
                                    <x-ui.badge variant="accent">{{ __('team.people.pending') }}</x-ui.badge>
                                </p>
                                <p class="text-muted text-xs">
                                    @if ($invitation->name !== null)
                                        <span class="inline-block">{{ $invitation->email }}</span> ·
                                    @endif
                                    {{ __('team.people.sent', ['when' => $invitation->updated_at->diffForHumans()]) }}
                                </p>
                            </div>
                            <div class="flex flex-none items-center gap-1">
                                <x-ui.icon-button icon="refresh-cw" size="sm" variant="ghost" :label="__('team.people.resend')" wire:click="resend({{ $invitation->id }})" />
                                <x-ui.icon-button icon="x" size="sm" variant="danger" :label="__('team.people.cancel')" wire:click="cancelInvitation({{ $invitation->id }})" />
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            @if ($team->hasDepartments)
                <x-ui.card class="bp-card">
                    <div class="bp-card-head">
                        <h2>{{ __('team.departments.title') }}</h2>
                        <span class="bp-chip font-mono">{{ $team->departments->count() }}</span>
                        <x-ui.button class="ml-auto" variant="secondary" size="sm" icon="plus" wire:click="openDepartment">
                            {{ __('team.departments.new') }}
                        </x-ui.button>
                    </div>
                    <p class="bp-card-sub">{{ __('team.departments.sub') }}</p>

                    @if ($team->departments->isEmpty())
                        <p class="text-muted py-4 text-center text-sm">{{ __('team.departments.empty') }}</p>
                    @endif

                    <div class="tm-depts">
                        @foreach ($team->departments as $room)
                            <div class="tm-dept-card" wire:key="dept-{{ $room->id }}">
                                <div class="flex items-start gap-2">
                                    <span class="tm-dept-icon"><x-icon name="layers" :size="18" /></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-strong text-sm font-semibold">{{ $room->name }}</p>
                                        <p class="text-muted text-xs">{{ __('team.departments.when') }}: {{ $room->routing_hint }}</p>
                                        <p class="tm-dept-hours">
                                            <x-icon name="clock" :size="12" />
                                            {{ $room->usesBusinessHours() ? __('team.hours.business') : __('team.hours.own', ['hours' => $room->scheduleLabel()]) }}
                                        </p>
                                    </div>
                                    <x-ui.icon-button icon="pencil" size="sm" variant="ghost" :label="__('team.departments.edit', ['name' => $room->name])" wire:click="openDepartment({{ $room->id }})" />
                                </div>
                                <div class="tm-dept-foot">
                                    @if ($room->users->isEmpty())
                                        <span class="text-muted text-xs">{{ __('team.departments.nobody') }}</span>
                                    @else
                                        <span class="tm-stack">
                                            @foreach ($room->users as $staff)
                                                <x-ui.avatar :name="$staff->name" size="xs" wire:key="m-{{ $room->id }}-{{ $staff->id }}" />
                                            @endforeach
                                        </span>
                                    @endif
                                    <span @class(['tm-waiting ml-auto', 'is-on' => $room->waiting > 0])>
                                        {{ $room->waiting > 0 ? trans_choice('team.departments.waiting', $room->waiting, ['count' => $room->waiting]) : __('team.departments.none_waiting') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @else
                @php($unlocks = Plan::lowestWithDepartments()->code)
                <x-ui.card class="bp-card">
                    <div class="bp-card-head">
                        <h2>{{ __('team.locked.title', ['plan' => __('plan.names.'.$unlocks)]) }}</h2>
                        <x-ui.button class="ml-auto" variant="secondary" size="sm" icon="gem" :href="route('my-plan')" wire:navigate>
                            {{ __('team.locked.cta') }}
                        </x-ui.button>
                    </div>
                    <p class="bp-card-sub">{{ __('team.locked.body') }}</p>
                </x-ui.card>
            @endif
        </div>

        <aside class="bp-rail">
            <x-ui.card class="bp-card">
                <p class="eyebrow mb-3">{{ __('team.how.title') }}</p>
                <ol class="tm-steps">
                    @foreach (['invite' => 'user-plus', 'route' => 'layers', 'notify' => 'message-circle'] as $step => $icon)
                        <li wire:key="step-{{ $step }}">
                            <span class="tm-step-icon"><x-icon :name="$icon" :size="16" /></span>
                            <span>{{ __('team.how.'.$step) }}</span>
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>
        </aside>
    </div>

    @if ($memberOpen)
        <x-ui.slide-over
            x-on:slide-over-close="$wire.set('memberOpen', false)"
            :title="$member->editingId === null ? __('team.invite.title') : $member->data->name"
            :subtitle="$member->editingId === null ? __('team.invite.sub') : $member->data->email"
        >
            <div class="bp-form">
                @if ($member->editingId === null)
                    <x-catalog.form-row>
                        <x-inputsform.input span="full" name="name" :label="__('team.invite.name')" wire:model="member.data.name" />
                    </x-catalog.form-row>
                    <x-catalog.form-row>
                        <x-inputsform.input span="full" name="email" type="email" icon="mail" :label="__('team.invite.email')" wire:model="member.data.email" required />
                    </x-catalog.form-row>
                @endif
                <x-catalog.form-row>
                    <x-inputsform.phone
                        span="full"
                        name="whatsapp"
                        :countries="$this->phoneCountries"
                        :default-dial="$this->defaultDial"
                        :value="$member->data->whatsapp !== null ? '+'.$member->data->whatsapp : null"
                        wire:model="member.data.whatsapp"
                        :label="__('team.invite.whatsapp')"
                        :hint="__('team.invite.whatsapp_hint')"
                    />
                </x-catalog.form-row>

                @if ($team->hasDepartments && $team->departments->isNotEmpty())
                    <div>
                        <p class="text-strong text-sm font-semibold">{{ __('team.invite.departments') }}</p>
                        <p class="text-muted mb-2 text-xs">{{ __('team.invite.departments_hint') }}</p>
                        <div class="flex flex-col gap-2">
                            @foreach ($team->departments as $room)
                                <x-ui.checkbox
                                    name="department_ids"
                                    :value="$room->id"
                                    :label="$room->name"
                                    :description="$room->routing_hint"
                                    wire:model="member.data.department_ids"
                                    wire:key="inv-{{ $room->id }}"
                                />
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <x-slot:footer>
                <x-ui.button variant="danger" wire:click="$set('memberOpen', false)">{{ __('team.invite.cancel') }}</x-ui.button>
                <x-ui.button class="ml-auto" :icon="$member->editingId === null ? 'send' : 'check'" wire:click="saveMember">
                    {{ $member->editingId === null ? __('team.invite.send') : __('team.people.save') }}
                </x-ui.button>
            </x-slot:footer>
        </x-ui.slide-over>
    @endif

    @if ($departmentOpen)
        <x-ui.slide-over
            x-on:slide-over-close="$wire.set('departmentOpen', false)"
            :title="$department->editingId === null ? __('team.departments.new') : __('team.departments.edit', ['name' => $department->data->name])"
        >
            <div class="bp-form">
                <x-catalog.form-row>
                    <x-inputsform.input span="full" name="name" :label="__('team.department.name')" wire:model="department.data.name" required />
                </x-catalog.form-row>
                <x-catalog.form-row>
                    <x-inputsform.textarea span="full" name="routing_hint" :rows="2" :label="__('team.departments.when')" :hint="__('team.department.when_hint')" wire:model="department.data.routing_hint" required />
                </x-catalog.form-row>

                <div>
                    <p class="text-strong mb-2 text-sm font-semibold">{{ __('team.department.people') }}</p>
                    <div class="flex flex-col gap-2">
                        @foreach ($team->members as $person)
                            <x-ui.checkbox
                                name="user_ids"
                                :value="$person->id"
                                :label="$person->name"
                                wire:model="department.data.user_ids"
                                wire:key="dp-{{ $person->id }}"
                            />
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="text-strong text-sm font-semibold">{{ __('team.hours.label') }}</p>
                    <p class="text-muted mb-2 text-xs">{{ __('team.hours.hint') }}</p>
                    <x-catalog.form-row>
                        <x-inputsform.switch-field
                            span="full"
                            name="uses_business_hours"
                            :label="__('team.hours.use_business')"
                            :on="__('team.hours.switch_on')"
                            :off="__('team.hours.switch_off')"
                            wire:model.live="department.data.uses_business_hours"
                        />
                    </x-catalog.form-row>

                    @unless ($department->data->uses_business_hours)
                        <div class="tm-hours">
                            @foreach ([1, 2, 3, 4, 5, 6, 0] as $day)
                                @php($dayName = ucfirst(now()->startOfWeek()->addDays(($day + 6) % 7)->locale(app()->getLocale())->isoFormat('ddd')))
                                <x-catalog.form-row class="tm-hour-row" wire:key="dh-{{ $day }}">
                                    <span class="tm-hour-day">{{ $dayName }}</span>
                                    <x-ui.switch name="week-{{ $day }}" size="sm" :aria-label="$dayName" wire:model.live="department.data.week.{{ $day }}.open" />
                                    @if ($department->data->week[$day]['open'])
                                        <x-inputsform.input span="code" size="s" maxlength="5" inputmode="numeric" class="font-mono" name="week.{{ $day }}.opens" :aria-label="$dayName.' · '.__('team.department.opens')" wire:model="department.data.week.{{ $day }}.opens" />
                                        <span class="bp-shift-sep" aria-hidden="true">–</span>
                                        <x-inputsform.input span="code" size="s" maxlength="5" inputmode="numeric" class="font-mono" name="week.{{ $day }}.closes" :aria-label="$dayName.' · '.__('team.department.closes')" wire:model="department.data.week.{{ $day }}.closes" />
                                    @else
                                        <span class="text-muted text-sm">{{ __('team.department.closed') }}</span>
                                    @endif
                                </x-catalog.form-row>
                            @endforeach
                        </div>
                    @endunless
                </div>
            </div>

            <x-slot:footer>
                @if ($department->editingId !== null)
                    <x-ui.icon-button
                        icon="trash-2"
                        variant="ghost"
                        :label="__('team.department.delete')"
                        x-data
                        x-on:click="dialog.confirm({
                            title: {{ Js::from(__('team.department.delete_title', ['name' => $department->data->name])) }},
                            message: {{ Js::from(__('team.department.delete_message')) }},
                            accept: {{ Js::from(__('team.department.delete')) }},
                            type: 'danger',
                        }).then((yes) => yes && $wire.deleteDepartment({{ $department->editingId }}))"
                    />
                @endif
                <x-ui.button variant="danger" wire:click="$set('departmentOpen', false)">{{ __('team.invite.cancel') }}</x-ui.button>
                <x-ui.button class="ml-auto" icon="check" wire:click="saveDepartment">{{ __('team.department.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.slide-over>
    @endif
</div>
