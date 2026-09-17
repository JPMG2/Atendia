<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\AsistenteAtendia;
use App\Enums\GuardVerdict;
use App\Enums\MessageDirection;
use App\Models\Business;
use App\Models\Conversation;
use App\Services\ConversationGuard;
use App\Services\EvolutionApi;
use App\Services\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Transcription;

/**
 * One inbound WhatsApp message, already acknowledged. Queued behind a
 * debounce window: a burst of texts collapses into one prompt, and only
 * the burst's LAST job answers. The reply leaves with the full human
 * ritual — read, typing proportional to length, short bubbles.
 */
class ProcessIncomingWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    /** Bubbles read human-sized; anything longer splits at paragraphs. */
    private const int BUBBLE_LIMIT = 300;

    private const int MAX_BUBBLES = 3;

    public function __construct(
        public string $instance,
        public string $from,
        public string $senderName,
        public string $text,
        public string $messageId,
        public ?string $audioBase64 = null,
    ) {}

    public function handle(): void
    {
        $business = Business::forWhatsAppInstance($this->instance);

        if ($business === null) {
            // A live instance nobody claimed: worth an alarm, not a retry.
            Log::warning('whatsapp.incoming.unclaimed', [
                'instance' => $this->instance,
                'from' => $this->from,
            ]);

            return;
        }

        // One reply pipeline per chat at a time; a parallel worker waits.
        Cache::lock("wa:lock:{$this->instance}:{$this->from}", 120)
            ->block(30, fn () => $this->reply($business));
    }

    private function reply(Business $business): void
    {
        $text = $this->audioBase64 !== null ? $this->transcribe() : $this->drainBuffer();

        if ($text === null || trim($text) === '') {
            return;
        }

        $evolution = app(EvolutionApi::class);

        $verdict = app(ConversationGuard::class)->verdict($this->instance, $this->from, $text);

        if ($verdict === GuardVerdict::Muted) {
            return;
        }

        if ($verdict !== GuardVerdict::Ok) {
            $evolution->sendText($this->instance, $this->from, __('assistant.guard.'.$verdict->value), 1500);

            return;
        }

        // Best effort, never at the reply's expense: the 👍 "heard you", the
        // read receipt and the early "typing…" while the model thinks.
        rescue(fn () => $evolution->react($this->instance, "{$this->from}@s.whatsapp.net", $this->messageId, '👍'), report: false);
        rescue(fn () => $evolution->markRead($this->instance, "{$this->from}@s.whatsapp.net", $this->messageId), report: false);
        rescue(fn () => $evolution->markComposing($this->instance, $this->from), report: false);

        // The worker has no session: the job adopts the business so the
        // assistant's knowledge search runs inside the right tenant. A failed
        // send throws out of here and the queue retries the whole exchange.
        app(Tenant::class)->for((int) $business->id, function () use ($business, $evolution, $text): void {
            $conversation = Conversation::query()->firstOrCreate(
                ['contact_phone' => $this->from],
                ['contact_name' => $this->senderName !== '' ? $this->senderName : null],
            );

            $reply = new AsistenteAtendia($business, $conversation)->answer($text)->text;

            foreach ($this->bubbles($reply) as $bubble) {
                $evolution->sendText($this->instance, $this->from, $bubble, $this->humanDelay($bubble));
            }

            $this->rememberExchange($conversation, $text, $reply);
            $this->rememberForDigest($business, $text, $reply);
        });
    }

    /**
     * Persists the turn AFTER answering: the agent's memory hands over
     * previous turns only, so the current text never rides twice.
     */
    private function rememberExchange(Conversation $conversation, string $question, string $reply): void
    {
        $conversation->messages()->create([
            'direction' => MessageDirection::In,
            'wa_message_id' => $this->messageId,
            'body' => $question,
        ]);
        $conversation->messages()->create([
            'direction' => MessageDirection::Out,
            'body' => $reply,
        ]);

        $conversation->fill([
            'contact_name' => $this->senderName !== '' ? $this->senderName : $conversation->contact_name,
            'last_message_at' => now(),
        ])->save();
    }

    /**
     * The owner's nightly digest reads from this short-lived tally: until
     * the conversations table exists, cache is the only ledger of the day.
     */
    private function rememberForDigest(Business $business, string $question, string $reply): void
    {
        $key = 'wa:digest:'.$business->id.':'.now()->format('Y-m-d');
        $entries = (array) Cache::get($key, []);

        if (count($entries) >= 200) {
            return;
        }

        $entries[] = [
            'from' => $this->from,
            'q' => mb_substr($question, 0, 200),
            'a' => mb_substr($reply, 0, 200),
        ];

        Cache::put($key, $entries, now()->addHours(36));
    }

    /**
     * Empties this chat's burst buffer, or refuses the turn: when a newer
     * message re-armed the debounce, ITS job owns the answer and this one
     * dies silently instead of double-replying.
     */
    private function drainBuffer(): ?string
    {
        $sender = "{$this->instance}:{$this->from}";
        $last = Cache::get("wa:last:{$sender}");

        if ($last !== null && $last !== $this->messageId) {
            return null;
        }

        $texts = (array) (Cache::pull("wa:buf:{$sender}") ?? [$this->text]);
        Cache::forget("wa:last:{$sender}");

        return trim(implode("\n", array_filter($texts, fn ($piece): bool => is_string($piece) && trim($piece) !== '')));
    }

    /** Voice notes become text and join the same pipeline; replies stay text. */
    private function transcribe(): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'wa-audio-');

        try {
            file_put_contents($path, base64_decode((string) $this->audioBase64, true) ?: '');

            return (string) Transcription::fromPath($path)->generate();
        } catch (\Throwable $e) {
            report($e);

            return null;
        } finally {
            @unlink($path);
        }
    }

    /**
     * Splits a long reply into up to three human-sized bubbles at paragraph
     * seams; the leftovers ride in the last one rather than spamming.
     *
     * @return list<string>
     */
    private function bubbles(string $reply): array
    {
        $reply = trim($reply);

        if (mb_strlen($reply) <= self::BUBBLE_LIMIT) {
            return [$reply];
        }

        $bubbles = [];

        foreach (preg_split('/\n{2,}/u', $reply) ?: [$reply] as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            $current = end($bubbles);

            if ($bubbles !== [] && (count($bubbles) === self::MAX_BUBBLES || mb_strlen($current."\n\n".$paragraph) <= self::BUBBLE_LIMIT)) {
                $bubbles[count($bubbles) - 1] = $current."\n\n".$paragraph;

                continue;
            }

            $bubbles[] = $paragraph;
        }

        return $bubbles === [] ? [$reply] : $bubbles;
    }

    /**
     * Typing time a person would need: base plus per-character, jittered so
     * no two replies land with machine-perfect cadence, capped at 8 s.
     */
    private function humanDelay(string $bubble): int
    {
        $millis = (1200 + mb_strlen($bubble) * 55) * random_int(75, 125) / 100;

        return (int) min($millis, 8000);
    }
}
