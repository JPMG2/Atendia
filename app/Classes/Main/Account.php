<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Account\CancelEmailChange;
use App\Actions\Account\ChangeAccountPassword;
use App\Actions\Account\ConfirmEmailChange;
use App\Actions\Account\RequestEmailChange;
use App\Actions\Account\SaveAccountAvatar;
use App\Actions\Account\UpdateAccountProfile;
use App\Actions\Account\VerifyAccountEmail;
use App\Dto\UserDto;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * The account piece: the person behind the business — name, login email
 * and password. Unlike the business pieces it exists from the first login,
 * so it is never null. Closing and restoring are life-cycle commands, not
 * profile slices: their callers run those Actions directly.
 */
class Account
{
    public function __construct(private User $user) {}

    public UserDto $data {
        get => UserDto::fromUser($this->user);
    }

    /** @param  array<string, mixed>  $validated */
    public function saveProfile(array $validated): User
    {
        return app(UpdateAccountProfile::class)->handle($this->user, $validated);
    }

    /** Null removes the photo and leaves the initials. */
    public function saveAvatar(?UploadedFile $file): User
    {
        return app(SaveAccountAvatar::class)->handle($this->user, $file);
    }

    public function requestEmailChange(string $newEmail): User
    {
        return app(RequestEmailChange::class)->handle($this->user, $newEmail);
    }

    public function confirmEmailChange(string $hash): bool
    {
        return app(ConfirmEmailChange::class)->handle($this->user, $hash);
    }

    public function verifyEmail(string $hash): bool
    {
        return app(VerifyAccountEmail::class)->handle($this->user, $hash);
    }

    public function cancelEmailChange(?string $hash = null): bool
    {
        return app(CancelEmailChange::class)->handle($this->user, $hash);
    }

    public function changePassword(string $password, bool $logoutOthers, string $currentFingerprint): User
    {
        return app(ChangeAccountPassword::class)->handle($this->user, $password, $logoutOthers, $currentFingerprint);
    }
}
