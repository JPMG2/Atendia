<?php

declare(strict_types=1);

namespace App\Dto;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * The account as the settings screen reads it: full row minus the secrets,
 * so every card hydrates from the same snapshot.
 */
final class UserDto
{
    public function __construct(
        public readonly string $name = '',
        public readonly string $email = '',
        public readonly ?string $pending_email = null,
        public readonly ?CarbonInterface $email_verified_at = null,
        public readonly ?CarbonInterface $created_at = null,
        public readonly ?CarbonInterface $password_changed_at = null,
        public readonly ?CarbonInterface $two_factor_whatsapp_at = null,
        public readonly ?string $avatar_url = null,
    ) {}

    public static function fromUser(User $user): self
    {
        // Read raw: a model created in this very request never loaded the
        // newer columns, and the strict-attributes guard would throw on them.
        $raw = $user->getAttributes();

        return new self(
            name: $user->name,
            email: $user->email,
            pending_email: $raw['pending_email'] ?? null,
            email_verified_at: $user->email_verified_at,
            created_at: $user->created_at,
            password_changed_at: isset($raw['password_changed_at']) ? $user->password_changed_at : null,
            two_factor_whatsapp_at: isset($raw['two_factor_whatsapp_at']) ? $user->two_factor_whatsapp_at : null,
            avatar_url: $user->avatarUrl(),
        );
    }

    public bool $isVerified {
        get => $this->email_verified_at !== null;
    }
}
