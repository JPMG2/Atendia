<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Team;

use App\Actions\Team\AcceptTeamInvitation;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Rules\AttributeValidator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * The guest side of an invitation: a name and a password turn the link into
 * a seat. No Client here — there is no signed-in user yet to compose one.
 */
class JoinTeamForm extends BaseForm
{
    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function save(TeamInvitation $invitation): NotificationDto
    {
        $validated = $this->validateServiceData();

        return $this->tryAction(function () use ($invitation, $validated): NotificationDto {

            $user = app(AcceptTeamInvitation::class)->handle($invitation, $validated);
            Auth::login($user);

            return new NotificationDto(__('team.join.welcome', ['business' => $user->business?->name]), NotificationType::Success);

        }, __('notifications.not_updated'));
    }

    protected function transformServiceData(): array
    {
        return [
            'name' => trim($this->name),
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ];
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return [
            'name' => AttributeValidator::stringValid(true, '2'),
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return [
            'name' => __('team.join.name'),
            'password' => __('team.join.password'),
        ];
    }
}
