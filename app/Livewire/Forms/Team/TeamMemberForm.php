<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Team;

use App\Classes\Main\Client;
use App\Dto\NotificationDto;
use App\Dto\TeamMemberDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\User;
use App\Rules\AttributeValidator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * The person sheet of "Equipo": a new seat is an invitation by email, an
 * existing one only changes where the handoffs reach them.
 */
class TeamMemberForm extends BaseForm
{
    public TeamMemberDto $data;

    public ?int $editingId = null;

    public function setup(?int $userId = null): void
    {
        $this->editingId = $userId;
        $this->resetErrorBag();

        $member = $userId === null ? null : $this->client()->team?->members->firstWhere('id', $userId);

        $this->data = $member === null
            ? new TeamMemberDto
            : new TeamMemberDto(
                name: $member->name,
                email: $member->email,
                whatsapp: $member->whatsapp,
                department_ids: $member->departments->pluck('id')->all(),
            );
    }

    public function save(): NotificationDto
    {
        $team = $this->client()->team;

        if ($team === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData($this->editingId);

        return $this->tryAction(function () use ($team, $validated): NotificationDto {

            if ($this->editingId === null) {
                $seats = $team->seats;

                if (! $team->canOfferSeatTo($validated['email'])) {
                    return new NotificationDto(__('team.notify.seats_full', [
                        'plan' => __('plan.names.'.$seats['plan']),
                        'cap' => $seats['cap'],
                    ]), NotificationType::Warning);
                }

                $team->invite($validated, Auth::user());

                return new NotificationDto(__('team.notify.invited', ['email' => $validated['email']]), NotificationType::Success);
            }

            $team->updateMember($this->editingId, $validated);

            return new NotificationDto(__('team.notify.member_saved'), NotificationType::Success);

        }, __('notifications.not_updated'));
    }

    /** Rebuilt per request: a Livewire form cannot hold it in a constructor. */
    private function client(): Client
    {
        return Client::for(Auth::user());
    }

    protected function transformServiceData(): array
    {
        $payload = $this->data->toPayload();

        // Without the plan's departments there is nowhere to route: nothing rides in.
        if (! ($this->client()->team?->hasDepartments ?? false)) {
            $payload['department_ids'] = [];
        }

        return $payload;
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        $businessId = Auth::user()?->business_id;

        return [
            // The login address: taken by any account, it cannot be offered again.
            'email' => $excludeId === null
                ? ['required', ...AttributeValidator::emailValid((new User)->getTable(), 'email')]
                : ['required', 'email:rfc'],
            'name' => ['nullable', ...AttributeValidator::stringValid(false, '2')],
            'whatsapp' => ['nullable', ...AttributeValidator::digitValid('8', false), 'max:30'],
            'department_ids' => ['array'],
            'department_ids.*' => ['integer', Rule::exists('departments', 'id')->where('business_id', $businessId)],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'email' => __('team.invite.email'),
            'name' => __('team.invite.name'),
            'whatsapp' => __('team.invite.whatsapp'),
            'department_ids' => __('team.invite.departments'),
        ];
    }
}
