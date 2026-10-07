<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Dto\IncidentRowDto;
use App\Enums\IncidentKind;
use App\Models\AssistantRating;
use App\Models\Conversation;
use App\Models\ConversationAnalysis;
use App\Models\ConversationQuestion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What went wrong across the platform, as one desk sorted by severity.
 *
 * Arrival order would be operationally wrong: a customer nobody answered
 * would sit behind threads a person is already handling. Rows come out by
 * `IncidentKind::weight()` first, age second, each carrying its evidence.
 */
final class Incidents
{
    /** @var Collection<int, IncidentRowDto>|null Built at most once per read. */
    private ?Collection $rows = null;

    private function __construct(public readonly CarbonImmutable $readAt) {}

    public static function now(): self
    {
        return new self(CarbonImmutable::now());
    }

    /** @var Collection<int, IncidentRowDto> */
    public Collection $all {
        get => $this->rows ??= $this->build();
    }

    public bool $isEmpty {
        get => $this->all->isEmpty();
    }

    /** @var Collection<string, int> How many of each kind, keyed by its value. */
    public Collection $counts {
        get => $this->all->groupBy(fn (IncidentRowDto $row): string => $row->kind->value)
            ->map(fn (Collection $group): int => $group->count());
    }

    public function countOf(IncidentKind $kind): int
    {
        return $this->counts->get($kind->value, 0);
    }

    /**
     * The business behind most of one problem, when it is behind more than
     * one. Four customers of the same business left waiting is ONE business
     * failing, not four accidents: read row by row that never shows up, and
     * it is the only reading that says who to call.
     *
     * @return array{business: string, businessId: int, count: int, total: int}|null
     */
    public function concentration(IncidentKind $kind): ?array
    {
        $rows = $this->ofKind($kind)->filter(fn (IncidentRowDto $row): bool => $row->businessId !== null);

        if ($rows->count() < self::CONCENTRATION_MIN) {
            return null;
        }

        /** @var Collection<int, IncidentRowDto> $worst */
        $worst = $rows->groupBy(fn (IncidentRowDto $row): int => (int) $row->businessId)
            ->sortByDesc(fn (Collection $group): int => $group->count())
            ->first();

        return $worst->count() < self::CONCENTRATION_MIN ? null : [
            'business' => (string) $worst->first()->business,
            'businessId' => (int) $worst->first()->businessId,
            'count' => $worst->count(),
            'total' => $rows->count(),
        ];
    }

    /** @var Collection<int, IncidentRowDto> */
    public function ofKind(?IncidentKind $kind): Collection
    {
        return $kind === null
            ? $this->all
            : $this->all->filter(fn (IncidentRowDto $row): bool => $row->kind === $kind)->values();
    }

    /**
     * @return Collection<int, IncidentRowDto>
     */
    private function build(): Collection
    {
        return $this->unanswered()
            ->merge($this->failedJobs())
            ->merge($this->repeated())
            ->merge($this->handoffs())
            ->merge($this->rejected())
            ->merge($this->upset())
            ->sortByDesc(fn (IncidentRowDto $row): string => sprintf('%03d-%011d', $row->weight, $row->minutesWaiting))
            ->pipe($this->onePerThread(...))
            ->values();
    }

    /**
     * One row per conversation, keeping its worst reading. A thread nobody
     * answered AND whose customer left annoyed is one problem with one phone
     * number behind it: listed twice it is counted twice, and the header
     * promises more work than there is. Failed jobs have no thread, so each
     * one stays.
     *
     * @param  Collection<int, IncidentRowDto>  $rows  Already sorted, worst first.
     * @return Collection<int, IncidentRowDto>
     */
    private function onePerThread(Collection $rows): Collection
    {
        return $rows->filter(fn (IncidentRowDto $row): bool => $row->conversationId === null)
            ->merge($rows->whereNotNull('conversationId')->unique('conversationId'))
            ->sortByDesc(fn (IncidentRowDto $row): string => sprintf('%03d-%011d', $row->weight, $row->minutesWaiting));
    }

    /** @return Collection<int, IncidentRowDto> */
    private function unanswered(): Collection
    {
        return Conversation::unanswered((int) config('atendia.incidents.unanswered_minutes'))
            ->map(fn (Conversation $thread): IncidentRowDto => new IncidentRowDto(
                kind: IncidentKind::Unanswered,
                happenedAt: CarbonImmutable::parse($thread->last_message_at),
                business: $thread->business?->name,
                businessId: $thread->business_id,
                customer: $thread->contact_name ?? $thread->contact_phone,
                excerpt: $thread->latestMessage?->body,
                conversationId: $thread->id,
            ))
            ->toBase();
    }

    /** @return Collection<int, IncidentRowDto> */
    private function handoffs(): Collection
    {
        return Conversation::handoffUnattended((int) config('atendia.handoff.reminder_minutes'))
            ->map(fn (Conversation $thread): IncidentRowDto => new IncidentRowDto(
                kind: IncidentKind::HandoffUnattended,
                happenedAt: CarbonImmutable::parse($thread->escalated_at),
                business: $thread->business?->name,
                businessId: $thread->business_id,
                customer: $thread->contact_name ?? $thread->contact_phone,
                excerpt: $thread->latestMessage?->body,
                conversationId: $thread->id,
            ))
            ->toBase();
    }

    /**
     * Threads where the customer asked twice and nobody answered. The excerpt
     * is the LAST question: the words they used when they were already tired.
     *
     * @return Collection<int, IncidentRowDto>
     */
    private function repeated(): Collection
    {
        return ConversationQuestion::repeatedUnanswered(self::REPEATED_DAYS)
            ->map(fn (ConversationQuestion $question): IncidentRowDto => new IncidentRowDto(
                kind: IncidentKind::CustomerRepeated,
                happenedAt: CarbonImmutable::parse($question->created_at),
                business: $question->business?->name,
                businessId: $question->business_id,
                customer: $question->conversation?->contact_name ?? $question->conversation?->contact_phone,
                excerpt: $question->question,
                conversationId: $question->conversation_id,
            ))
            ->toBase();
    }

    /**
     * Answers the business itself marked as wrong. It is the only signal here
     * that somebody who KNOWS the business judged the assistant, so it comes
     * with the answer that was rejected, not with the customer's question.
     *
     * @return Collection<int, IncidentRowDto>
     */
    private function rejected(): Collection
    {
        return AssistantRating::rejected(self::REJECTED_DAYS)
            ->map(fn (AssistantRating $mark): IncidentRowDto => new IncidentRowDto(
                kind: IncidentKind::AnswerRejected,
                happenedAt: CarbonImmutable::parse($mark->created_at),
                business: $mark->business?->name,
                businessId: $mark->business_id,
                customer: $mark->message?->conversation?->contact_name ?? $mark->message?->conversation?->contact_phone,
                excerpt: $mark->message?->body,
                conversationId: $mark->message?->conversation_id,
            ))
            ->toBase();
    }

    /** @return Collection<int, IncidentRowDto> */
    private function upset(): Collection
    {
        return ConversationAnalysis::upset(self::UPSET_DAYS)
            ->map(fn (ConversationAnalysis $reading): IncidentRowDto => new IncidentRowDto(
                kind: IncidentKind::CustomerUpset,
                happenedAt: CarbonImmutable::parse($reading->created_at),
                business: $reading->business?->name,
                businessId: $reading->business_id,
                customer: $reading->conversation?->contact_name ?? $reading->conversation?->contact_phone,
                conversationId: $reading->conversation_id,
            ))
            ->toBase();
    }

    /**
     * A failed job has no business and no customer: the exception's first
     * line is the only evidence there is, and it is what names the problem.
     *
     * @return Collection<int, IncidentRowDto>
     */
    private function failedJobs(): Collection
    {
        return DB::table('failed_jobs')
            ->where('failed_at', '>=', $this->readAt->subDays(self::JOBS_DAYS))
            ->orderByDesc('failed_at')
            ->limit(self::JOBS_LIMIT)
            ->get(['failed_at', 'exception'])
            ->map(fn (object $job): IncidentRowDto => new IncidentRowDto(
                kind: IncidentKind::JobFailed,
                happenedAt: CarbonImmutable::parse($job->failed_at),
                excerpt: str((string) $job->exception)->before("\n")->limit(160)->toString(),
            ));
    }

    /** Two of the same problem in one business is already a pattern worth naming. */
    private const int CONCENTRATION_MIN = 2;

    /** A thread read as annoyed stays on the desk for a day. */
    private const int UPSET_DAYS = 1;

    /** Insisting without an answer is worth chasing for two days. */
    private const int REPEATED_DAYS = 2;

    /**
     * A rejected answer stays a week: unlike the rest it is not an event that
     * passes but a defect that keeps answering wrong until somebody teaches it.
     */
    private const int REJECTED_DAYS = 7;

    private const int JOBS_DAYS = 1;

    /** Fifty identical failures are one problem: the list stays readable. */
    private const int JOBS_LIMIT = 20;
}
