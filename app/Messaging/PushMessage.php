<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * What the push channel demands of a message. A push shows on a locked screen
 * and travels through Expo and Google, so a message declares a line that is
 * safe to show there: never a customer's name, phone or words.
 */
abstract class PushMessage
{
    public function __construct(public Model $model) {}

    abstract public function title(): string;

    abstract public function body(): string;

    /**
     * What the app reads to decide where to go when the notice is tapped.
     * Identifiers of screens only: anything else would ride in the clear.
     *
     * @return array<string, string>
     */
    public function data(): array
    {
        return [];
    }

    /** Whether this person wants the message at all (their own mutes, mainly). */
    public function wants(User $user): bool
    {
        return true;
    }

    /**
     * What a person is left alone about for a while after a push. One kind
     * (news in the bell) must not swallow another (the day's report).
     */
    public function quietKey(): string
    {
        return class_basename(static::class);
    }

    /** The number on the app icon for this person; null leaves it alone. */
    public function badgeFor(User $user): ?int
    {
        return null;
    }
}
