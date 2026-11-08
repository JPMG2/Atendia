<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The landing sales chat's funnel, tallied per day: opened, answers read,
 * handed off to sales. Platform-wide numbers — never tenant data, and never
 * what a visitor wrote.
 */
#[Fillable(['day', 'opens', 'answers', 'handoffs'])]
class SalesChatMetric extends Model
{
    public const array COUNTERS = ['opens', 'answers', 'handoffs'];

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
        if (! in_array($counter, self::COUNTERS, true)) {
            return;
        }

        static::query()->firstOrCreate(['day' => now()->toDateString()])->increment($counter);
    }
}
