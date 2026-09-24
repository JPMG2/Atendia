<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Enums\MessageDirection;
use App\Enums\QuestionResolution;
use App\Enums\SuggestionStatus;
use App\Models\Business;
use App\Models\ConversationMessage;
use App\Models\ConversationQuestion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The "Mis estadísticas" piece: every number the screen shows, computed
 * from the tenant's own threads. Each block also hands back its READING —
 * the GBP habit: a chart without its sentence is homework, not insight.
 */
class Statistics
{
    public function __construct(private Business $business) {}

    /** The business's clock: its months, days and peak hours, not UTC's. */
    private string $timezone {
        get => $this->business->localTimezone();
    }

    /** "Now" on the business's clock; boundaries built from it convert back to UTC for the queries. */
    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone);
    }

    /** A UTC-stored timestamp column read on the business's clock, for grouping in SQL. */
    private function local(string $column): string
    {
        return "({$column} at time zone 'UTC' at time zone ".DB::getPdo()->quote($this->timezone).')';
    }

    /**
     * @return array{conversations: int, new_contacts: int, questions: int, audio_minutes: int, resolution: int, recovered: int}
     */
    public function monthKpis(?CarbonImmutable $month = null): array
    {
        $month = ($month ?? $this->now())->setTimezone($this->timezone);
        [$from, $to] = [$month->startOfMonth()->utc(), $month->endOfMonth()->utc()];

        $inbound = $this->inbound()->whereBetween('created_at', [$from, $to]);
        $threadIds = (clone $inbound)->distinct('conversation_id')->pluck('conversation_id');

        return [
            'conversations' => $threadIds->count(),
            'new_contacts' => $this->business->conversations()->whereBetween('created_at', [$from, $to])->count(),
            'questions' => $this->questions()->whereBetween('asked_at', [$from, $to])->count(),
            'audio_minutes' => (int) ceil((clone $inbound)->sum('audio_seconds') / 60),
            'resolution' => $this->resolutionRate($from, $to),
            'recovered' => $this->recoveredCustomers($from, $to),
        ];
    }

    /**
     * Customers who got a taught answer late this month and wrote back
     * after it: the ones teaching won back.
     */
    private function recoveredCustomers(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->questions()
            ->whereBetween('customer_notified_at', [$from, $to])
            ->whereExists(fn ($reply) => $reply->selectRaw('1')
                ->from('conversation_messages')
                ->whereColumn('conversation_messages.conversation_id', 'conversation_questions.conversation_id')
                ->where('conversation_messages.direction', MessageDirection::In->value)
                ->whereColumn('conversation_messages.created_at', '>', 'conversation_questions.customer_notified_at'))
            ->distinct()
            ->count('conversation_questions.conversation_id');
    }

    /**
     * The number every competitor sells, measured per QUESTION: a thread
     * where the assistant solved three of four is not a failure.
     */
    private function resolutionRate(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $month = $this->questions()->whereBetween('asked_at', [$from, $to]);
        $total = (clone $month)->count();

        return $total === 0 ? 0 : (int) round($month->where('resolved_by', QuestionResolution::Assistant)->count() / $total * 100);
    }

    /**
     * Active conversations per day, zero-filled so the chart never lies by
     * skipping quiet days.
     *
     * @return list<array{date: string, label: string, count: int}>
     */
    public function dailySeries(int $days = 30): array
    {
        $from = $this->now()->startOfDay()->subDays($days - 1);

        $rows = $this->inbound()
            ->where('created_at', '>=', $from->utc())
            ->selectRaw('date('.$this->local('created_at').') as day, count(distinct conversation_id) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $from->addDays($i);
            $series[] = [
                'date' => $date->toDateString(),
                'label' => $date->format('d/m'),
                'count' => (int) ($rows[$date->toDateString()] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * The daily chart's sentence: the strongest day of the window.
     *
     * @return array{label: string, count: int}|null
     */
    public function bestDay(int $days = 30): ?array
    {
        $best = collect($this->dailySeries($days))->sortByDesc('count')->first();

        return ($best['count'] ?? 0) > 0
            ? ['label' => CarbonImmutable::parse($best['date'])->translatedFormat('l j'), 'count' => $best['count']]
            : null;
    }

    /**
     * Inbound volume by hour plus the busiest contiguous 4-hour window —
     * the sentence the owner acts on ("have someone alert 9 to 13").
     *
     * @var array{counts: list<int>, window: array{from: int, to: int, share: int}|null}
     */
    public array $peakHours {
        get {
            $rows = $this->inbound()
                ->where('created_at', '>=', $this->now()->startOfMonth()->utc())
                ->selectRaw('extract(hour from '.$this->local('created_at').')::int as hour, count(*) as total')
                ->groupBy('hour')
                ->pluck('total', 'hour');

            $counts = array_map(fn (int $hour): int => (int) ($rows[$hour] ?? 0), range(0, 23));
            $total = array_sum($counts);

            if ($total === 0) {
                return ['counts' => $counts, 'window' => null];
            }

            $bestFrom = 0;
            $bestSum = -1;

            for ($from = 0; $from <= 20; $from++) {
                $sum = array_sum(array_slice($counts, $from, 4));

                if ($sum > $bestSum) {
                    [$bestFrom, $bestSum] = [$from, $sum];
                }
            }

            return ['counts' => $counts, 'window' => [
                'from' => $bestFrom,
                'to' => $bestFrom + 4,
                'share' => (int) round($bestSum / $total * 100),
            ]];
        }
    }

    /**
     * The retention counter: everything the assistant handled since day one.
     *
     * @var array{since: CarbonImmutable|null, questions: int, conversations: int}
     */
    public array $sinceDayOne {
        get {
            $first = $this->inbound()->min('created_at');

            return [
                'since' => $first === null ? null : CarbonImmutable::parse($first, 'UTC')->setTimezone($this->timezone),
                // The sentence says the ASSISTANT answered them: the team's and the unanswered do not count.
                'questions' => $this->questions()->where('resolved_by', QuestionResolution::Assistant)->count(),
                'conversations' => $this->business->conversations()->count(),
            ];
        }
    }

    /**
     * Active conversations per month, oldest first.
     *
     * @return list<array{label: string, count: int}>
     */
    public function monthlyTrend(int $months = 6): array
    {
        $trend = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = $this->now()->startOfMonth()->subMonthsNoOverflow($i);
            $trend[] = [
                'label' => $month->translatedFormat('M'),
                // Only the thread count: the full KPI set cost six extra queries per month.
                'count' => $this->inbound()
                    ->whereBetween('created_at', [$month->utc(), $month->endOfMonth()->utc()])
                    ->distinct('conversation_id')
                    ->count('conversation_id'),
            ];
        }

        return $trend;
    }

    /**
     * The centrepiece: this month's questions by topic, most asked first,
     * with the share the assistant solved alone and what fixes the rest —
     * teaching an answer, or a catalog item customers ask for and miss.
     *
     * @var list<array{topic: string, asked: int, alone: int, action: ?string}>
     */
    public array $topics {
        get {
            $month = $this->now()->startOfMonth();
            $rows = $this->topicCounts($month->utc(), $month->endOfMonth()->utc());
            $previous = $this->topicCounts($month->subMonthNoOverflow()->utc(), $month->subSecond()->utc())->pluck('asked', 'topic');
            $samples = $this->topicSamples($month->utc());
            $hasCatalog = $this->hasCatalog();

            return $rows->map(fn (object $row): array => [
                'topic' => $row->topic ?? __('client.assistant.suggestions_other'),
                'asked' => (int) $row->asked,
                'alone' => (int) round($row->alone / $row->asked * 100),
                // No previous month, no arrow: a new topic has nothing to compare against.
                'delta' => isset($previous[$row->topic]) ? (int) round(($row->asked - $previous[$row->topic]) / $previous[$row->topic] * 100) : null,
                'samples' => $samples[$row->topic ?? ''] ?? [],
                'action' => match (true) {
                    $hasCatalog && $row->not_offered > 0 => 'catalog',
                    $row->to_teach > 0 => 'teach',
                    default => null,
                },
            ])->all();
        }
    }

    /**
     * Per topic in a window: asked, solved alone, failed on something not
     * in the catalog, and still waiting in the teaching queue.
     *
     * @return Collection<int, object{topic: ?string, asked: int, alone: int, not_offered: int, to_teach: int}>
     */
    private function topicCounts(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->questions()
            ->whereBetween('conversation_questions.asked_at', [$from, $to])
            ->leftJoin('question_intents', 'question_intents.id', '=', 'conversation_questions.question_intent_id')
            ->leftJoin('knowledge_suggestions', 'knowledge_suggestions.id', '=', 'conversation_questions.knowledge_suggestion_id')
            ->groupBy('question_intents.name')
            ->selectRaw('question_intents.name as topic, count(*) as asked')
            ->selectRaw('count(*) filter (where resolved_by = ?) as alone', [QuestionResolution::Assistant->value])
            ->selectRaw('count(*) filter (where resolved_by <> ? and subject is not null and service_id is null and product_id is null) as not_offered', [QuestionResolution::Assistant->value])
            ->selectRaw('count(*) filter (where knowledge_suggestions.status = ?) as to_teach', [SuggestionStatus::Pending->value])
            ->orderByDesc('asked')
            ->toBase()
            ->get();
    }

    /**
     * The freshest real questions of each topic this month, so a row can
     * unfold into what customers actually wrote.
     *
     * @return array<string, list<array{question: string, resolved_by: string}>> topic name ('' = none) => samples
     */
    private function topicSamples(CarbonImmutable $from): array
    {
        return $this->questions()
            ->where('conversation_questions.asked_at', '>=', $from)
            ->leftJoin('question_intents', 'question_intents.id', '=', 'conversation_questions.question_intent_id')
            ->latest('conversation_questions.id')
            ->limit(500)
            ->toBase()
            ->get(['question_intents.name as topic', 'conversation_questions.question', 'conversation_questions.resolved_by'])
            ->groupBy(fn (object $row): string => (string) $row->topic)
            ->map(fn ($rows): array => $rows->take(5)->map(fn (object $row): array => ['question' => $row->question, 'resolved_by' => $row->resolved_by])->values()->all())
            ->all();
    }

    /**
     * The premium jewel: what customers asked for by name this month and
     * the catalog does not have — demand walking away. Silent with an empty
     * catalog, where everything would read as missing.
     *
     * @return list<array{sample: string, count: int}>
     */
    public function catalogGaps(int $limit = 3): array
    {
        if (! $this->hasCatalog()) {
            return [];
        }

        return $this->questions()
            ->where('asked_at', '>=', $this->now()->startOfMonth()->utc())
            ->whereNotNull('subject')
            ->whereNull('service_id')
            ->whereNull('product_id')
            ->selectRaw('(array_agg(subject order by id desc))[1] as sample, count(*) as total')
            ->groupByRaw('lower(subject)')
            ->orderByDesc('total')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn (object $gap): array => ['sample' => Str::ucfirst((string) $gap->sample), 'count' => (int) $gap->total])
            ->all();
    }

    private function hasCatalog(): bool
    {
        return $this->business->services()->exists() || $this->business->products()->exists();
    }

    /**
     * Every analyzed customer question, the base of every "questions"
     * figure; `inbound()` stays for activity (threads, hours).
     *
     * @return Builder<ConversationQuestion>
     */
    private function questions(): Builder
    {
        return ConversationQuestion::query()->where('conversation_questions.business_id', $this->business->id);
    }

    /** @return Builder<ConversationMessage> */
    private function inbound()
    {
        return ConversationMessage::query()
            ->where('business_id', $this->business->id)
            ->where('direction', MessageDirection::In);
    }
}
