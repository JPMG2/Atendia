<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Dto\AdoptionRowDto;
use App\Enums\AdoptionSituation;
use App\Enums\AdoptionStep;
use App\Models\AdoptionMark;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The "Adopción" screen as data: the funnel, the three tabs and who was
 * already written to. The web screen and the mobile API both read from here,
 * so the phone cannot count a stalled account the panel does not.
 */
class AdoptionBoard
{
    /** @var Collection<int, AdoptionRowDto>|null */
    private ?Collection $loaded = null;

    /** @var array<string, array{at: CarbonImmutable, by: int|null, name: string|null}>|null */
    private ?array $notes = null;

    public readonly AdoptionSituation $situation;

    /** A tab nobody knows falls back to the one that asks for action. */
    public function __construct(?string $view = null)
    {
        $this->situation = AdoptionSituation::tryFrom((string) $view) ?? AdoptionSituation::Stalled;
    }

    /** @var Collection<int, AdoptionRowDto> */
    public Collection $accounts {
        get => $this->loaded ??= User::adoptionRows();
    }

    /** @var Collection<int, AdoptionRowDto>|null */
    private ?Collection $tab = null;

    /** @var Collection<int, AdoptionRowDto> the open tab, the longest-waiting first */
    public Collection $rows {
        get => $this->tab ??= User::adoptionTab($this->situation, $this->accounts);
    }

    /** @var array<string, int>|null */
    private ?array $tally = null;

    /** @var array<string, int> how many accounts each tab holds */
    public array $counts {
        get => $this->tally ??= collect(AdoptionSituation::cases())
            ->mapWithKeys(fn (AdoptionSituation $case): array => [
                $case->value => $this->accounts->filter(fn (AdoptionRowDto $row): bool => $row->situation === $case)->count(),
            ])
            ->all();
    }

    /**
     * One bar per step: how many accounts got AT LEAST that far, and how many
     * of them stopped right there. The last step stops nobody.
     *
     * @var Collection<int, array{step: AdoptionStep, reached: int, stopped: int|null, share: float}>
     */
    public Collection $flow {
        get {
            $total = max(1, $this->accounts->count());
            $reached = collect(AdoptionStep::cases())->map(
                fn (AdoptionStep $step): int => $this->accounts->filter(fn (AdoptionRowDto $row): bool => $row->step->position() >= $step->position())->count(),
            );

            return collect(AdoptionStep::cases())->map(fn (AdoptionStep $step, int $index): array => [
                'step' => $step,
                'reached' => $reached[$index],
                'stopped' => isset($reached[$index + 1]) ? $reached[$index] - $reached[$index + 1] : null,
                'share' => round($reached[$index] / $total * 100, 1),
            ]);
        }
    }

    /** @var array<string, array{at: CarbonImmutable, by: int|null, name: string|null}> by "email|step" */
    public array $written {
        get => $this->notes ??= AdoptionMark::lastWritten();
    }

    /**
     * Who last wrote to this account on the step it is on now, in words, or
     * null. "Hace 0 segundos" reads as a bug, and with more than one person on
     * the team "you wrote" is not always true.
     */
    public function writtenNote(AdoptionRowDto $row, ?int $viewerId): ?string
    {
        $note = $this->written[$row->email.'|'.$row->step->value] ?? null;

        if ($note === null) {
            return null;
        }

        $when = $note['at']->diffInSeconds(now()) < 60 ? __('adoption.written.now') : $note['at']->diffForHumans();

        return $note['by'] === $viewerId || $note['name'] === null
            ? __('adoption.written.you', ['when' => $when])
            : __('adoption.written.other', ['name' => $note['name'], 'when' => $when]);
    }

    /** Why the row is listed, in words: a row that cannot say why it is here is noise. */
    public function why(AdoptionRowDto $row): string
    {
        return $row->situation === AdoptionSituation::Active
            ? __('adoption.why.answered', ['conversations' => trans_choice('adoption.conversations_count', $row->conversations, ['count' => $row->conversations])])
            : __('adoption.why.'.$row->step->value);
    }

    /** The rule that put it here, with its days; an active account has none. */
    public function rule(AdoptionRowDto $row): ?string
    {
        return $row->situation === AdoptionSituation::Active
            ? null
            : trans_choice('adoption.rule.'.$row->situation->value, $row->daysInStep, ['count' => $row->daysInStep, 'limit' => $row->stallLimit]);
    }

    /** How long since it last came in, or that it never came back. */
    public function idle(AdoptionRowDto $row): string
    {
        return $row->daysIdle === null
            ? __('adoption.never_returned')
            : trans_choice('adoption.idle', $row->daysIdle, ['count' => $row->daysIdle]);
    }

    /** "Desde el …": the day it reached the step it is on. */
    public function since(AdoptionRowDto $row): string
    {
        return __('adoption.since', ['date' => ($row->reachedAt($row->step) ?? $row->registeredAt)->format('d/m/Y')]);
    }
}
