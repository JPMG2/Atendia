<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdoptionMarkKind;
use App\Enums\AdoptionStep;
use Carbon\CarbonImmutable;
use Database\Factories\AdoptionMarkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform-level, not tenant data: it is about the accounts, from the admin's
 * side, so it carries no business_id.
 */
#[Fillable(['user_id', 'step', 'kind', 'created_by'])]
class AdoptionMark extends Model
{
    /** @use HasFactory<AdoptionMarkFactory> */
    use HasFactory;

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'step' => AdoptionStep::class,
            'kind' => AdoptionMarkKind::class,
        ];
    }

    /** Notes that something was done about an account on a step; false when the account is not there. */
    public static function record(string $email, AdoptionStep $step, AdoptionMarkKind $kind, ?int $by = null): bool
    {
        $userId = User::query()->where('email', $email)->value('id');

        if ($userId === null) {
            return false;
        }

        self::query()->create(['user_id' => $userId, 'step' => $step, 'kind' => $kind, 'created_by' => $by]);

        return true;
    }

    /**
     * The last time each account was written to on the step it sits on, and who
     * did it: with two people on the team, "you wrote" is not always true.
     *
     * @return array<string, array{at: CarbonImmutable, by: int|null, name: string|null}> By "email|step".
     */
    public static function lastWritten(): array
    {
        return self::query()
            ->join('users', 'users.id', '=', 'adoption_marks.user_id')
            ->leftJoin('users as writers', 'writers.id', '=', 'adoption_marks.created_by')
            ->where('adoption_marks.kind', AdoptionMarkKind::Written->value)
            ->orderBy('adoption_marks.created_at')
            ->get(['users.email as email', 'adoption_marks.step as step', 'adoption_marks.created_at as at', 'adoption_marks.created_by as by', 'writers.name as writer'])
            ->mapWithKeys(fn (self $row): array => [
                $row->getAttribute('email').'|'.$row->getRawOriginal('step') => [
                    'at' => CarbonImmutable::parse($row->getRawOriginal('at')),
                    'by' => $row->getRawOriginal('by') === null ? null : (int) $row->getRawOriginal('by'),
                    'name' => $row->getAttribute('writer'),
                ],
            ])
            ->all();
    }

    /**
     * When each account was last marked, by "email|step": a mark on an earlier
     * step is a different key, so it never shows against the step it is on now.
     *
     * @return array<string, CarbonImmutable>
     */
    public static function lastBy(AdoptionMarkKind $kind): array
    {
        return self::query()
            ->join('users', 'users.id', '=', 'adoption_marks.user_id')
            ->where('adoption_marks.kind', $kind->value)
            ->selectRaw('users.email as email, adoption_marks.step as step, max(adoption_marks.created_at) as at')
            ->groupBy('users.email', 'adoption_marks.step')
            ->get()
            ->mapWithKeys(fn (self $row): array => [
                $row->getAttribute('email').'|'.$row->getRawOriginal('step') => CarbonImmutable::parse($row->getAttribute('at')),
            ])
            ->all();
    }
}
