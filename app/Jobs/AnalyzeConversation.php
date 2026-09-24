<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\ConversationAnalyst;
use App\Enums\CustomerSentiment;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\MessageKind;
use App\Enums\QuestionResolution;
use App\Models\Conversation;
use App\Models\ConversationAnalysis;
use App\Models\ConversationMessage;
use App\Models\QuestionIntent;
use App\Services\Knowledge\KnowledgeEmbedder;
use App\Services\Tenant;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Analyzes the stretch of a thread written since the last analysis, once
 * it has finished. Unique per thread: the scheduler may find it again
 * before the worker is done with it.
 */
class AnalyzeConversation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Earlier turns shown as context: enough for "¿y los sábados?" to make sense. */
    private const int CONTEXT_MESSAGES = 6;

    public function __construct(public int $businessId, public int $conversationId) {}

    public function uniqueId(): string
    {
        return (string) $this->conversationId;
    }

    public function handle(KnowledgeEmbedder $embedder): void
    {
        app(Tenant::class)->for($this->businessId, function () use ($embedder): void {
            $conversation = Conversation::query()->find($this->conversationId);

            if ($conversation === null) {
                return;
            }

            $watermark = (int) $conversation->analyzed_message_id;
            $stretch = $this->messages($conversation)->where('id', '>', $watermark)->oldest('id')->get();

            if ($stretch->isEmpty()) {
                return;
            }

            // The word list is the free first filter: small talk alone never costs a call.
            $asked = $stretch->contains(fn (ConversationMessage $message): bool => $message->direction === MessageDirection::In
                && ConversationMessage::looksLikeEnquiry((string) $message->body));

            if (! $asked) {
                $conversation->forceFill(['analyzed_message_id' => $stretch->last()->id])->saveQuietly();

                return;
            }

            // A dead model leaves the watermark alone: the next run retries.
            try {
                $intents = QuestionIntent::forAnalysis();
                $response = new ConversationAnalyst($intents)->prompt($this->transcript($conversation, $watermark, $stretch));
            } catch (Throwable $e) {
                report($e);

                return;
            }

            $questions = $this->questions((array) ($response['questions'] ?? []), $stretch, $intents);
            $vectors = rescue(fn (): array => $embedder->embed(array_column($questions, 'question')), [], report: false);

            DB::transaction(function () use ($conversation, $stretch, $response, $questions, $vectors): void {
                $analysis = ConversationAnalysis::query()->create([
                    'conversation_id' => $conversation->id,
                    'first_message_id' => $stretch->first()->id,
                    'last_message_id' => $stretch->last()->id,
                    'sentiment' => CustomerSentiment::tryFrom((string) ($response['sentiment'] ?? '')) ?? CustomerSentiment::Neutral,
                ]);

                foreach ($questions as $index => $question) {
                    $analysis->questions()->create([
                        ...$question,
                        'conversation_id' => $conversation->id,
                        'embedding' => $vectors[$index] ?? null,
                    ]);
                }

                $conversation->forceFill(['analyzed_message_id' => $stretch->last()->id])->saveQuietly();
            });
        });
    }

    /**
     * @return Builder<ConversationMessage>
     */
    private function messages(Conversation $conversation): Builder
    {
        return ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('kind', MessageKind::Message);
    }

    /** @param  Collection<int, ConversationMessage>  $stretch */
    private function transcript(Conversation $conversation, int $watermark, Collection $stretch): string
    {
        $context = $this->messages($conversation)
            ->where('id', '<=', $watermark)
            ->latest('id')
            ->limit(self::CONTEXT_MESSAGES)
            ->get()
            ->reverse()
            ->map(fn (ConversationMessage $message): string => $this->speaker($message).': '.$message->body)
            ->implode("\n");

        $lines = $stretch->values()
            ->map(fn (ConversationMessage $message, int $index): string => '['.($index + 1).'] '.$this->speaker($message).': '.$message->body)
            ->implode("\n");

        return ($context !== '' ? "Contexto previo (ya analizado):\n{$context}\n\n" : '')."Tramo a analizar:\n{$lines}";
    }

    private function speaker(ConversationMessage $message): string
    {
        return match (true) {
            $message->direction === MessageDirection::In => 'Cliente',
            $message->author === MessageAuthor::Human => 'Equipo',
            default => 'Asistente',
        };
    }

    /**
     * Keeps only questions anchored to a customer message of THIS stretch:
     * the model's numbering is checked, never trusted.
     *
     * @param  array<int, mixed>  $raw
     * @param  Collection<int, ConversationMessage>  $stretch
     * @param  array<string, array{id: int, name: string, description: string}>  $intents
     * @return list<array{conversation_message_id: int, question_intent_id: ?int, question: string, subject: ?string, resolved_by: QuestionResolution}>
     */
    private function questions(array $raw, Collection $stretch, array $intents): array
    {
        $numbered = $stretch->values();
        $questions = [];

        foreach ($raw as $item) {
            $message = $numbered->get(((int) ($item['message'] ?? 0)) - 1);
            $text = trim((string) ($item['question'] ?? ''));

            if ($message === null || $message->direction !== MessageDirection::In || $text === '') {
                continue;
            }

            $subject = trim((string) ($item['subject'] ?? ''));

            $questions[] = [
                'conversation_message_id' => (int) $message->id,
                'question_intent_id' => $intents[(string) ($item['intent'] ?? '')]['id'] ?? null,
                'question' => Str::limit($text, 497),
                'subject' => $subject !== '' ? Str::limit($subject, 117) : null,
                'resolved_by' => QuestionResolution::tryFrom((string) ($item['resolved_by'] ?? '')) ?? QuestionResolution::Nobody,
            ];
        }

        return $questions;
    }
}
