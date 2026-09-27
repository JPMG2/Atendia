<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Database\Factories\TeamInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A seat offered by email. Only the SHA-256 of the link's token is stored:
 * a leaked table row never opens a door.
 */
#[Fillable(['business_id', 'name', 'email', 'whatsapp', 'department_ids', 'token_hash', 'expires_at', 'invited_by'])]
class TeamInvitation extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<TeamInvitationFactory> */
    use HasFactory;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** The invitation behind a mail link, or null when it never existed, was used or expired. */
    public static function findValid(string $token): ?self
    {
        $invitation = self::query()->where('token_hash', self::hashToken($token))->first();

        return $invitation === null || $invitation->isExpired() ? null : $invitation;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'department_ids' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
