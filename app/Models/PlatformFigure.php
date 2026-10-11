<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A number a job computed ahead of time so the admin screen does not count it
 * on every open. It always travels with the moment it was computed: a stored
 * figure that cannot say how old it is would look like a live one.
 *
 * Platform accounting, so it carries no `business_id` and no tenant scope.
 */
#[Fillable(['key', 'payload', 'computed_at'])]
class PlatformFigure extends Model
{
    /** The businesses with the most movement, by WhatsApp and by AI. */
    public const string ACTIVITY = 'business_activity';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    /**
     * Stores a figure, replacing the previous one under the same key: a re-run
     * corrects instead of piling up rows nobody reads.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function put(string $key, array $payload): self
    {
        return self::query()->updateOrCreate(
            ['key' => $key],
            ['payload' => $payload, 'computed_at' => now()],
        );
    }

    /** The figure as the job left it, or null when it never ran. */
    public static function read(string $key): ?self
    {
        return self::query()->where('key', $key)->first();
    }

    /** Older than the job's cadence allows: the job is late or dead. */
    public function isOlderThan(int $minutes): bool
    {
        return $this->computed_at->lt(now()->subMinutes($minutes));
    }
}
