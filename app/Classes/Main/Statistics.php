<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Enums\MessageDirection;
use App\Models\Business;
use App\Models\ConversationMessage;
use App\Models\KnowledgeChunk;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * The "Mis estadísticas" piece: every number the screen shows, computed
 * from the tenant's own threads. Each block also hands back its READING —
 * the GBP habit: a chart without its sentence is homework, not insight.
 */
class Statistics
{
    /** Meaning distance for "same question": below it, two texts are one topic. */
    private const float CLUSTER_THRESHOLD = 0.62;

    /** Cosine DISTANCE ceiling for "the catalog covers it"; loose on purpose. */
    private const float CATALOG_MATCH_DISTANCE = 0.50;

    private const int CLUSTER_SAMPLE = 200;

    public function __construct(private Business $business) {}

    /**
     * @return array{conversations: int, new_contacts: int, questions: int, audio_minutes: int}
     */
    public function monthKpis(?CarbonImmutable $month = null): array
    {
        $month ??= CarbonImmutable::now();
        [$from, $to] = [$month->startOfMonth(), $month->endOfMonth()];

        $inbound = $this->inbound()->whereBetween('created_at', [$from, $to]);

        return [
            'conversations' => (clone $inbound)->distinct('conversation_id')->count('conversation_id'),
            'new_contacts' => $this->business->conversations()->whereBetween('created_at', [$from, $to])->count(),
            'questions' => (clone $inbound)->count(),
            'audio_minutes' => (int) ceil((clone $inbound)->sum('audio_seconds') / 60),
        ];
    }

    /**
     * Active conversations per day, zero-filled so the chart never lies by
     * skipping quiet days.
     *
     * @return list<array{date: string, label: string, count: int}>
     */
    public function dailySeries(int $days = 30): array
    {
        $from = CarbonImmutable::today()->subDays($days - 1);

        $rows = $this->inbound()
            ->where('created_at', '>=', $from)
            ->selectRaw('date(created_at) as day, count(distinct conversation_id) as total')
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
                ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth())
                ->selectRaw('extract(hour from created_at)::int as hour, count(*) as total')
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
                'since' => $first === null ? null : CarbonImmutable::parse($first),
                'questions' => $this->inbound()->count(),
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
            $month = CarbonImmutable::now()->subMonths($i);
            $trend[] = [
                'label' => $month->translatedFormat('M'),
                'count' => $this->monthKpis($month)['conversations'],
            ];
        }

        return $trend;
    }

    /**
     * The month's questions grouped by MEANING over the stored embeddings —
     * no tagging, no model call: greedy clustering, cached for the day.
     *
     * @return list<array{sample: string, count: int}>
     */
    public function topAsked(int $limit = 5): array
    {
        return array_slice(
            array_values(array_filter($this->clusters(), fn (array $cluster): bool => $cluster['count'] > 1)),
            0,
            $limit,
        );
    }

    /**
     * The premium jewel: topics asked for that the catalog never mentions —
     * demand walking away. Silent without an indexed offer (no false claims).
     *
     * @return list<array{sample: string, count: int}>
     */
    public function catalogGaps(int $limit = 3): array
    {
        if (! $this->offerChunks()->exists()) {
            return [];
        }

        $gaps = [];

        // Postgres does the math: one HNSW lookup per candidate topic beats
        // hauling every offer embedding into PHP (the house pgvector pattern).
        foreach (array_slice($this->clusters(), 0, 10) as $cluster) {
            if ($cluster['count'] < 2) {
                continue;
            }

            $distance = $this->offerChunks()
                ->selectVectorDistance('embedding', $cluster['centroid'], as: 'distance')
                ->orderByVectorDistance('embedding', $cluster['centroid'])
                ->first()
                ?->getAttribute('distance');

            if ($distance !== null && (float) $distance > self::CATALOG_MATCH_DISTANCE) {
                $gaps[] = ['sample' => $cluster['sample'], 'count' => $cluster['count']];
            }

            if (count($gaps) === $limit) {
                break;
            }
        }

        return $gaps;
    }

    /**
     * The indexed offer: what the catalog already tells the assistant.
     *
     * @return Builder<KnowledgeChunk>
     */
    private function offerChunks(): Builder
    {
        return KnowledgeChunk::query()
            ->where('business_id', $this->business->id)
            ->whereHas('document', fn (Builder $query) => $query->whereIn('source_type', ['services', 'products']));
    }

    /**
     * Greedy meaning clusters over this month's inbound questions. Daily
     * cache: the dot products are the expensive part, not the query.
     *
     * @return list<array{sample: string, count: int, centroid: list<float>}>
     */
    private function clusters(): array
    {
        $key = 'wa:stats:clusters:'.$this->business->id.':'.now()->format('Y-m-d');

        return Cache::remember($key, now()->addHours(6), function (): array {
            $messages = $this->inbound()
                ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth())
                ->whereNotNull('embedding')
                ->latest()
                ->limit(self::CLUSTER_SAMPLE)
                ->get(['body', 'embedding']);

            $clusters = [];

            foreach ($messages as $message) {
                $embedding = (array) $message->embedding;

                foreach ($clusters as &$cluster) {
                    if ($this->dot($embedding, $cluster['centroid']) >= self::CLUSTER_THRESHOLD) {
                        $cluster['count']++;

                        continue 2;
                    }
                }

                unset($cluster);

                $clusters[] = [
                    'sample' => mb_substr(trim((string) $message->body), 0, 80),
                    'count' => 1,
                    'centroid' => $embedding,
                ];
            }

            usort($clusters, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

            return $clusters;
        });
    }

    /** @return Builder<ConversationMessage> */
    private function inbound()
    {
        return ConversationMessage::query()
            ->where('business_id', $this->business->id)
            ->where('direction', MessageDirection::In);
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function dot(array $a, array $b): float
    {
        $sum = 0.0;

        foreach ($a as $i => $value) {
            $sum += $value * (float) ($b[$i] ?? 0);
        }

        return $sum;
    }
}
