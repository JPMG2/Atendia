<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Ten one-time backup codes, GitHub-style: the way in when the phone is
 * lost. Returned in clear ONCE for the owner to keep; only hashes are saved,
 * and a new set always replaces the old one.
 */
class GenerateRecoveryCodes
{
    private const int COUNT = 10;

    /** @return list<string> */
    public function handle(User $user): array
    {
        $codes = [];

        for ($i = 0; $i < self::COUNT; $i++) {
            $codes[] = Str::lower(Str::random(5).'-'.Str::random(5));
        }

        $user->two_factor_recovery_codes = array_map(self::hash(...), $codes);
        $user->save();

        return $codes;
    }

    public static function hash(string $code): string
    {
        return hash('sha256', Str::lower(trim($code)));
    }
}
