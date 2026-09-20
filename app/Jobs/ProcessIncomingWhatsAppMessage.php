<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\AsistenteAtendia;
use App\Classes\Main\Plan;
use App\Enums\ConversationStatus;
use App\Enums\GuardVerdict;
use App\Enums\MessageDirection;
use App\Events\WhatsAppExchangeArrived;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\PlatformContact;
use App\Services\ConversationGuard;
use App\Services\EvolutionApi;
use App\Services\Knowledge\KnowledgeEmbedder;
use App\Services\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\Data\Usage;
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
        public int $audioSeconds = 0,
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
        $plan = $business->plan();
        $evolution = app(EvolutionApi::class);

        // The plan gate on audio closes BEFORE transcription spends a cent.
        // The customer only hears a courteous ask for text — the owner's
        // plan is never their problem.
        if ($this->audioBase64 !== null && ! $this->audioWithinPlan($business, $plan)) {
            $evolution->sendText($this->instance, $this->from, __('assistant.plan.audio'), 1500);

            return;
        }

        $text = $this->audioBase64 !== null ? $this->transcribe() : $this->drainBuffer();

        if ($text === null || trim($text) === '') {
            return;
        }

        $verdict = app(ConversationGuard::class)->verdict($this->instance, $this->from, $text, $plan->messagesPerHour);

        if ($verdict === GuardVerdict::Muted) {
            return;
        }

        if ($verdict !== GuardVerdict::Ok) {
            $evolution->sendText($this->instance, $this->from, __('assistant.guard.'.$verdict->value), 1500);

            return;
        }

        // Best effort, never at the reply's expense: the read receipt and the
        // early "typing…" while the model thinks. No auto-reactions: a thumbs
        // up on a QUESTION reads wrong (owner's call, 2026-09-17).
        rescue(fn () => $evolution->markRead($this->instance, "{$this->from}@s.whatsapp.net", $this->messageId), report: false);
        rescue(fn () => $evolution->markComposing($this->instance, $this->from), report: false);

        // The worker has no session: the job adopts the business so the
        // assistant's knowledge search runs inside the right tenant. A failed
        // send throws out of here and the queue retries the whole exchange.
        app(Tenant::class)->for((int) $business->id, function () use ($business, $evolution, $text): void {
            $customer = $this->rememberCustomer();

            $conversation = Conversation::query()->firstOrCreate(
                ['contact_phone' => $this->from],
                ['contact_name' => $this->senderName !== '' ? $this->senderName : null, 'customer_id' => $customer->id],
            );

            // Threads older than the customer layer adopt their person here.
            if ($conversation->customer_id === null) {
                $conversation->update(['customer_id' => $customer->id]);
            }

            $this->rememberPlatformContact($customer, $conversation);

            // A thread handed to the team belongs to the team: the assistant
            // stays quiet HERE (never account-wide), only keeping the record.
            if ($conversation->status === ConversationStatus::Team) {
                $this->rememberInboundOnly($conversation, $text);
                WhatsAppExchangeArrived::dispatch((int) $business->id, (int) $conversation->id);

                return;
            }

            $agent = new AsistenteAtendia($business, $conversation, $customer);
            $reply = $agent->answer($text)->text;

            foreach ($this->bubbles($reply) as $bubble) {
                $evolution->sendText($this->instance, $this->from, $bubble, $this->humanDelay($bubble));
            }

            $this->rememberExchange($conversation, $text, $reply, $agent->exchangeUsage);
            $this->rememberForDigest($business, $text, $reply);
            $this->warnOwnerNearCap($business, $evolution);

            WhatsAppExchangeArrived::dispatch((int) $business->id, (int) $conversation->id);
        });
    }

    /**
     * The customer record behind this phone, born on the first message and
     * freshened on every one after (tenant-scoped by the isolation layer).
     */
    private function rememberCustomer(): Customer
    {
        $customer = Customer::query()->firstOrCreate(
            ['phone' => $this->from],
            ['first_seen_at' => now()],
        );

        $customer->recordExchange($this->senderName !== '' ? $this->senderName : null);

        return $customer;
    }

    /**
     * AtendIa's own layer: the same person across every business. Linked by
     * id and fed by increments, so no query ever reads across tenants.
     */
    private function rememberPlatformContact(Customer $customer, Conversation $conversation): void
    {
        $contact = PlatformContact::query()->firstOrCreate(
            ['phone' => $this->from],
            ['first_seen_at' => now()],
        );

        if ($customer->platform_contact_id === null) {
            $customer->platformContact()->associate($contact)->save();
        }

        if ($conversation->wasRecentlyCreated) {
            $customer->increment('conversations_count');
        }

        $contact->recordExchange(
            newBusiness: $customer->wasRecentlyCreated,
            newConversation: $conversation->wasRecentlyCreated,
            language: $conversation->language,
            countryCode: $customer->country_code,
        );
    }

    /**
     * One heads-up per month to the owner's human number when usage crosses
     * 80% of the plan. Informative only: the assistant NEVER stops answering
     * at the cap — the WhatsApp is the client's cash register.
     */
    private function warnOwnerNearCap(Business $business, EvolutionApi $evolution): void
    {
        $plan = $business->plan();
        $used = $business->conversationsThisMonth();

        if ($used < (int) ($plan->conversationsPerMonth * 0.8) || $business->fallback_whatsapp_number === null) {
            return;
        }

        $sentKey = 'wa:cap80:'.$business->id.':'.now()->format('Y-m');

        if (! Cache::add($sentKey, true, now()->addDays(45))) {
            return;
        }

        rescue(fn () => $evolution->sendText(
            $this->instance,
            (string) preg_replace('/\D/', '', (string) $business->fallback_whatsapp_number),
            __('assistant.plan.cap_warning', [
                'used' => $used,
                'cap' => $plan->conversationsPerMonth,
                'url' => route('my-plan'),
            ]),
        ), report: true);
    }

    /**
     * Persists the turn AFTER answering: the agent's memory hands over
     * previous turns only, so the current text never rides twice.
     */
    private function rememberExchange(Conversation $conversation, string $question, string $reply, ?Usage $usage): void
    {
        $inbound = $conversation->messages()->create([
            'direction' => MessageDirection::In,
            'wa_message_id' => $this->messageId,
            'body' => $question,
            'audio_seconds' => $this->audioSeconds > 0 ? $this->audioSeconds : null,
        ]);

        // Best effort: search is a luxury, the stored thread is not.
        rescue(fn () => $inbound->update([
            'embedding' => app(KnowledgeEmbedder::class)->embedOne($question),
        ]), report: false);
        // The exchange's bill lives on the reply row: it is what the plan
        // caps will be priced against.
        $conversation->messages()->create([
            'direction' => MessageDirection::Out,
            'body' => $reply,
            'prompt_tokens' => $usage?->promptTokens,
            'completion_tokens' => $usage?->completionTokens,
        ]);

        $conversation->fill([
            'contact_name' => $this->senderName !== '' ? $this->senderName : $conversation->contact_name,
            'last_message_at' => now(),
        ])->save();
    }

    /** The paused thread still records what the customer says, reply-less. */
    private function rememberInboundOnly(Conversation $conversation, string $text): void
    {
        $inbound = $conversation->messages()->create([
            'direction' => MessageDirection::In,
            'wa_message_id' => $this->messageId,
            'body' => $text,
            'audio_seconds' => $this->audioSeconds > 0 ? $this->audioSeconds : null,
        ]);

        rescue(fn () => $inbound->update([
            'embedding' => app(KnowledgeEmbedder::class)->embedOne($text),
        ]), report: false);

        $conversation->fill([
            'contact_name' => $this->senderName !== '' ? $this->senderName : $conversation->contact_name,
            'last_message_at' => now(),
        ])->save();
    }

    /**
     * The owner's nightly digest reads from this short-lived tally: cheaper
     * than re-querying the day's threads, and gone by itself after sending.
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

    /** Audio rides only while the plan includes it AND the month's minutes last. */
    private function audioWithinPlan(Business $business, Plan $plan): bool
    {
        if (! $plan->allowsAudio) {
            return false;
        }

        return $business->audioSecondsThisMonth() < $plan->audioMinutesPerMonth * 60;
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
     * no two replies land with machine-perfect cadence, capped at 4 s: the
     * 2026 market median answers in under 5 s, and fast replies to inbound
     * chats carry no ban risk — only mass outbound does.
     */
    private function humanDelay(string $bubble): int
    {
        $millis = (1200 + mb_strlen($bubble) * 55) * random_int(75, 125) / 100;

        return (int) min($millis, 4000);
    }
}
