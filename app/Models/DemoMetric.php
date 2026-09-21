<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The hero demo's funnel, tallied per day: tried, consumed, registered.
 * Platform-wide numbers for the admin dashboard — never tenant data.
 */
#[Fillable(['day', 'sessions', 'messages', 'registrations'])]
class DemoMetric extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'date',
        ];
    }

    /** One counter, one bump, today's row: the whole funnel writes through here. */
    public static function bump(string $counter): void
    {
        static::query()->firstOrCreate(['day' => now()->toDateString()])->increment($counter);
    }
}
