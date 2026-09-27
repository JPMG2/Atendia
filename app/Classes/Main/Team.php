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
