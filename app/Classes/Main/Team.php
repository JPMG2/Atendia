<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Team\CancelTeamInvitation;
use App\Actions\Team\DeleteDepartment;
use App\Actions\Team\InviteTeamMember;
use App\Actions\Team\RemoveTeamMember;
use App\Actions\Team\ResendTeamInvitation;
use App\Actions\Team\SaveDepartment;
use App\Actions\Team\UpdateTeamMember;
use App\Enums\ConversationStatus;
use App\Models\Business;
use App\Models\Country;
use App\Models\Department;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The team piece: the people working next to the assistant and the
 * departments a handoff lands in. Owner-only by the route; every write is
 * pinned to THIS business, whatever ids a request carries.
 */
class Team
{
    public function __construct(private Business $business) {}

    /**
     * The owner first, then the agents by name.
     *
     * @var Collection<int, User>
     */
    public Collection $members {
        get => $this->business->users()
            ->with('departments:id,name', 'roles:id,name')
            ->withCount(['assignedConversations as open_threads' => fn ($open) => $open->whereIn('status', [ConversationStatus::Team, ConversationStatus::Customer])])
            ->get()
            ->sortBy(fn (User $user): string => ($user->isAgent() ? '1' : '0').mb_strtolower($user->name))
            ->values();
    }

    /** @var Collection<int, TeamInvitation> */
    public Collection $invitations {
        get => $this->business->teamInvitations()->latest()->get();
    }

    /** @var Collection<int, Department> */
    public Collection $departments {
        get => $this->business->departments()
            ->with('users:id,name')
            ->withCount(['conversations as waiting' => fn ($waiting) => $waiting->where('status', ConversationStatus::Team)])
            ->get();
    }

    /** Departments are a plan feature; without it the whole team gets every handoff. */
    public bool $hasDepartments {
        get => $this->business->plan()->hasDepartments;
    }

    /**
     * The plan's seats: everybody in the panel, the owner included, plus the
     * offers still open, against the cap.
     *
     * @var array{used: int, cap: int, plan: string}
     */
    public array $seats {
        get => [
            'used' => $this->business->teamSeatsUsed(),
            'cap' => $this->business->plan()->teamSeats,
            'plan' => $this->business->plan()->code,
        ];
    }

    /**
     * The team as the app draws it: people, open offers, departments and the
     * seats used, from the same reads the web screen makes.
     *
     * @var array{
     *     seats: array{used: int, cap: int, plan: string},
     *     hasDepartments: bool,
     *     copy: array<string, mixed>,
     *     locked: array{title: string, body: string, cta: string}|null,
     *     members: list<array<string, mixed>>,
     *     invitations: list<array<string, mixed>>,
     *     departments: list<array<string, mixed>>
     * }
     */
    public array $overview {
        get => [
            'seats' => $this->seats,
            'hasDepartments' => $this->hasDepartments,
            'defaultDial' => Country::dialCode($this->business->country_id),
            // The web screen's own words, so the phone never writes a sentence of its own.
            'copy' => [
                'title' => __('team.title'),
                'sub' => __('team.sub'),
                'people' => collect(['title', 'sub', 'invite', 'you', 'pending', 'resend', 'cancel', 'edit', 'remove', 'save', 'no_whatsapp', 'all_departments', 'available', 'away', 'remove_accept', 'cancel_message', 'cancel_accept', 'cancel_keep'])
                    ->mapWithKeys(fn (string $key): array => [$key => __('team.people.'.$key)])->all(),
                'invite' => collect(['title', 'sub', 'name', 'email', 'whatsapp', 'whatsapp_hint', 'departments', 'departments_hint', 'send', 'cancel'])
                    ->mapWithKeys(fn (string $key): array => [$key => __('team.invite.'.$key)])->all(),
                'departments' => collect(['title', 'sub', 'empty', 'when', 'nobody'])
                    ->mapWithKeys(fn (string $key): array => [$key => __('team.departments.'.$key)])->all(),
            ],
            // Without the plan's departments the web shows what unlocks them instead of the list.
            'locked' => $this->hasDepartments ? null : [
                'title' => __('team.locked.title', ['plan' => __('plan.names.'.Plan::lowestWithDepartments()->code)]),
                'body' => __('team.locked.body'),
                'cta' => __('team.locked.cta'),
            ],
            'members' => $this->members->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'whatsapp' => $member->whatsapp,
                'phone' => $this->phoneBoxes($member->whatsapp),
                'isAgent' => $member->isAgent(),
                'role' => __('team.roles.'.($member->isAgent() ? 'agent' : 'owner')),
                'isAvailable' => (bool) $member->is_available,
                'openThreads' => (int) $member->open_threads,
                // Removing never strands a customer: the sentence says where the open threads go.
                'removeTitle' => __('team.people.remove_title', ['name' => $member->name]),
                'removeMessage' => $member->open_threads > 0
                    ? __('team.people.remove_message', ['count' => $member->open_threads])
                    : __('team.people.remove_message_none'),
                'departmentIds' => $member->departments->pluck('id')->all(),
                'departments' => $member->departments->pluck('name')->all(),
            ])->all(),
            'invitations' => $this->invitations->map(fn (TeamInvitation $invitation): array => [
                'id' => $invitation->id,
                'name' => $invitation->name,
                'email' => $invitation->email,
                'whatsapp' => $invitation->whatsapp,
                'role' => __('team.roles.agent'),
                'departmentIds' => $invitation->department_ids ?? [],
                'label' => $invitation->name ?? $invitation->email,
                'sentLabel' => __('team.people.sent', ['when' => $invitation->updated_at->diffForHumans()]),
                'cancelTitle' => __('team.people.cancel_title', ['name' => $invitation->name ?? $invitation->email]),
                'sentAt' => $invitation->updated_at->toIso8601String(),
                'expired' => $invitation->isExpired(),
            ])->all(),
            'departments' => $this->departments->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
                'hint' => $department->routing_hint,
                'hours' => $department->usesBusinessHours()
                    ? __('team.hours.business')
                    : __('team.hours.own', ['hours' => $department->scheduleLabel()]),
                'waiting' => (int) $department->waiting,
                'waitingLabel' => $department->waiting > 0
                    ? trans_choice('team.departments.waiting', $department->waiting, ['count' => $department->waiting])
                    : __('team.departments.none_waiting'),
                'people' => $department->users->pluck('name')->all(),
            ])->all(),
        ];
    }

    /**
     * The WhatsApp as the web's phone box holds it: dial code and number. Seats
     * store bare digits, so the sheet reads them with the "+" it adds itself.
     *
     * @return array{dial: string|null, number: string}
     */
    private function phoneBoxes(?string $digits): array
    {
        return Country::splitPhone($digits === null ? null : '+'.$digits, Country::dialCode($this->business->country_id));
    }

    public function canOfferSeatTo(string $email): bool
    {
        return $this->business->canOfferSeatTo($email);
    }

    /** @param  array{name: string, email: string, whatsapp: ?string, department_ids: list<int>}  $validated */
    public function invite(array $validated, User $inviter): TeamInvitation
    {
        return app(InviteTeamMember::class)->handle($this->business, $validated, $inviter);
    }

    public function resend(int $invitationId, User $inviter): TeamInvitation
    {
        return app(ResendTeamInvitation::class)->handle($this->business->teamInvitations()->findOrFail($invitationId), $inviter);
    }

    public function cancel(int $invitationId): void
    {
        app(CancelTeamInvitation::class)->handle($this->business->teamInvitations()->findOrFail($invitationId));
    }

    /** @param  array{whatsapp: ?string, department_ids: list<int>}  $validated */
    public function updateMember(int $userId, array $validated): User
    {
        return app(UpdateTeamMember::class)->handle($this->business, $this->agent($userId), $validated);
    }

    /** @return int the open threads that went back to their department */
    public function remove(int $userId): int
    {
        return app(RemoveTeamMember::class)->handle($this->agent($userId));
    }

    /** @param  array{name: string, routing_hint: string, uses_business_hours: bool, week: array<int, array{open: bool, opens: string, closes: string}>, user_ids: list<int>}  $validated */
    public function saveDepartment(array $validated, ?int $id = null): Department
    {
        return app(SaveDepartment::class)->handle($this->business, $validated, $id);
    }

    public function deleteDepartment(int $id): void
    {
        app(DeleteDepartment::class)->handle($this->business->departments()->findOrFail($id));
    }

    /** Only agents of this business: the owner's own seat is never edited or removed from here. */
    private function agent(int $userId): User
    {
        $member = $this->business->users()->findOrFail($userId);

        abort_unless($member->isAgent(), 403);

        return $member;
    }
}
