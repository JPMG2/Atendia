<?php

declare(strict_types=1);

namespace App\Services\Topics;

use App\Ai\Agents\QuestionMatcher;
use App\Enums\QuestionResolution;
use App\Models\ConversationQuestion;
use App\Models\KnowledgeSuggestion;
use App\Services\Knowledge\KnowledgeEmbedder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Folds the current tenant's unanswered questions into suggestions: one
 * per distinct question, however many times and ways it was asked. The
 * vector settles the obvious; the matcher only sees the gray zone.
 */
class SuggestionCollector
{
    private const int BATCH = 30;

    private const int CANDIDATES = 4;

    public function __construct(private KnowledgeEmbedder $embedder) {}

    /**
     * Questions stored while the embedder was down get their vector late:
     * without it they never fold, and the inbox search cannot find them.
     */
    public function embedMissing(): void
    {
        $questions = ConversationQuestion::query()->whereNull('embedding')->oldest('id')->limit(self::BATCH)->get();

        if ($questions->isEmpty()) {
            return;
        }

        $vectors = $this->embedder->embed($questions->pluck('question')->all());

        foreach ($questions->values() as $index => $question) {
            if (isset($vectors[$index])) {
                $question->forceFill(['embedding' => $vectors[$index]])->save();
            }
        }
    }

    /** @return int how many questions it linked; 0 means nothing left or the matcher is down */
    public function collect(): int
    {
        // Without a vector a question cannot find its twins: it waits for
        // embedMissing() instead of founding a duplicate suggestion.
        $questions = ConversationQuestion::query()
            ->whereIn('resolved_by', [QuestionResolution::Team, QuestionResolution::Nobody])
            ->whereNull('knowledge_suggestion_id')
            ->whereNotNull('embedding')
            ->oldest('id')
            ->limit(self::BATCH)
            ->get();

        if ($questions->isEmpty()) {
            return 0;
        }

        $auto = (float) config('atendia.analysis.same_question_similarity');
        $floor = (float) config('atendia.analysis.question_candidate_similarity');
        $candidates = [];

        foreach ($questions as $question) {
            $near = $question->embedding === null ? [] : array_values(array_filter(
                KnowledgeSuggestion::closestMany($question->embedding, self::CANDIDATES),
                fn (array $candidate): bool => $candidate['similarity'] >= $floor,
            ));

            if (($near[0]['similarity'] ?? 0) >= $auto) {
                $this->link($question, KnowledgeSuggestion::query()->findOrFail($near[0]['id']));

                continue;
            }

            $candidates[$question->id] = $near;
        }

        $pending = $questions->whereIn('id', array_keys($candidates))->values();

        // A dead matcher leaves them unlinked: the next sweep retries.
        try {
            $matches = $this->needsJudge($pending, $candidates, $floor) ? $this->judge($pending, $candidates) : [];
        } catch (Throwable $e) {
            report($e);

            return $questions->count() - $pending->count();
        }

        $linked = [];

        foreach ($pending as $index => $question) {
            $number = $index + 1;
            $suggestionId = $matches[$number]['suggestion'] ?? 0;
            $sameAs = $matches[$number]['same_as'] ?? 0;

            $suggestion = match (true) {
                in_array($suggestionId, array_column($candidates[$question->id], 'id'), true) => KnowledgeSuggestion::query()->findOrFail($suggestionId),
                $sameAs > 0 && $sameAs < $number && isset($linked[$sameAs]) => $linked[$sameAs],
                default => KnowledgeSuggestion::query()->create([
                    'question_intent_id' => $question->question_intent_id,
                    'question' => $question->question,
                    'embedding' => $question->embedding,
                ]),
            };

            $this->link($question, $suggestion);
            $linked[$number] = $suggestion;
        }

        return $questions->count();
    }

    private function link(ConversationQuestion $question, KnowledgeSuggestion $suggestion): void
    {
        $question->forceFill(['knowledge_suggestion_id' => $suggestion->id])->save();
        $suggestion->absorbFailure($question);
    }

    /**
     * Only worth a call when some question has a candidate, or two of the
     * new ones might be the same.
     *
     * @param  Collection<int, ConversationQuestion>  $pending
     * @param  array<int, list<array{id: int, question: string, similarity: float}>>  $candidates
     */
    private function needsJudge(Collection $pending, array $candidates, float $floor): bool
    {
        if (array_filter($candidates) !== []) {
            return true;
        }

        $vectors = $pending->pluck('embedding')->filter()->values()->all();

        foreach ($vectors as $i => $a) {
            foreach (array_slice($vectors, $i + 1) as $b) {
                if ($this->similarity($a, $b) >= $floor) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, ConversationQuestion>  $pending
     * @param  array<int, list<array{id: int, question: string, similarity: float}>>  $candidates
     * @return array<int, array{suggestion: int, same_as: int}> question number => verdict
     */
    private function judge(Collection $pending, array $candidates): array
    {
        $existing = collect($candidates)->flatten(1)->unique('id')
            ->map(fn (array $candidate): string => "[{$candidate['id']}] {$candidate['question']}")
            ->implode("\n");

        $new = $pending->values()
            ->map(fn (ConversationQuestion $question, int $index): string => ($index + 1).'. '.$question->question)
            ->implode("\n");

        $response = new QuestionMatcher()->prompt("Preguntas nuevas:\n{$new}\n\nSugerencias existentes:\n".($existing !== '' ? $existing : '(ninguna)'));

        return collect((array) ($response['matches'] ?? []))
            ->mapWithKeys(fn (array $match): array => [(int) ($match['question'] ?? 0) => [
                'suggestion' => (int) ($match['suggestion'] ?? 0),
                'same_as' => (int) ($match['same_as'] ?? 0),
            ]])
            ->all();
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function similarity(array $a, array $b): float
    {
        $dot = $normA = $normB = 0.0;

        foreach ($a as $i => $value) {
            $dot += $value * $b[$i];
            $normA += $value * $value;
            $normB += $b[$i] * $b[$i];
        }

        return $normA > 0 && $normB > 0 ? $dot / sqrt($normA * $normB) : 0.0;
    }
}
