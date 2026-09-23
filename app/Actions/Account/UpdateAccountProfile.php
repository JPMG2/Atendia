<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Support\Arr;

class UpdateAccountProfile
{
    private const array COLUMNS = ['name'];

    /** @param  array<string, mixed>  $data  Already validated by the calling form. */
    public function handle(User $user, array $data): User
    {
        $user->fill(Arr::only($data, self::COLUMNS))->save();

        return $user;
    }
}
