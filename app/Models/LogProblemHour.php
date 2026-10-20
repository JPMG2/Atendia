<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many times a problem happened in one hour: the unit every period and
 * every trend on the logs screen is summed from. Platform-level, no tenant.
 */
#[Fillable(['log_problem_id', 'hour', 'occurrences'])]
class LogProblemHour extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['hour' => 'datetime', 'occurrences' => 'integer'];
    }

    /** @return BelongsTo<LogProblem, $this> */
    public function problem(): BelongsTo
    {
        return $this->belongsTo(LogProblem::class, 'log_problem_id');
    }
}
