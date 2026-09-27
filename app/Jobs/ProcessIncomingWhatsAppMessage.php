<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Business\SendHumanReply;
use App\Actions\Business\TranslateForCustomer;
use App\Ai\Agents\AsistenteAtendia;
use App\Classes\Main\Plan;
use App\Enums\ConversationStatus;
use App\Enums\GuardVerdict;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Events\WhatsAppExchangeArrived;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\PlatformContact;
use App\Services\ConversationGuard;
use App\Services\EvolutionApi;
use App\Services\OwnerPings;
use App\Services\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Transcription;
use Smalot\PdfParser\Parser as PdfParser;

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

    /**
     * Once: a retry re-asked the AI (paid, maybe a different answer) and
     * re-sent every bubble. The inbound is stored BEFORE answering, so a
     * failure leaves it in the panel and the analysis instead of nowhere.
     */
    public int $tries = 1;

    /** A PDF past this goes to the team unread: the model bills every page. */
    private const int PDF_MAX_BYTES = 5 * 1024 * 1024;

    /** Two model passes plus the bubbles, under the queue's 90s retry_after. */
    public int $timeout = 85;

    public function __construct(
        public string $instance,
        public string $from,
        public string $senderName,
        public string $text,
        public string $messageId,
        public ?string $audioBase64 = null,
        public int $audioSeconds = 0,
        public ?string $quotedMessageId = null,
        /** @var array{kind: string, tmp: ?string, mime: string, name: string, caption: string, lat: ?float, lng: ?float, label: string}|null */
        public ?array $media = null,
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

        // The owner's own phone is NEVER a customer: opening a thread for it
        // made the assistant escalate the owner to the owner (2026-09-20).
        // Their texts are relayed to the thread waiting for the team.
        if ($business->isOwnerWhatsApp($this->from)) {
            $this->relayOwnerReply($business, $evolution);

            return;
        }

        app(Tenant::class)->speakingAs($business, function () use ($business, $plan, $evolution): void {
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

            // A resolved thread reopens on the customer's next word: the
            // assistant takes it back like any fresh question.
            if ($conversation->status === ConversationStatus::Resolved) {
                $conversation->update(['status' => ConversationStatus::Open]);
            }

            // Unpaid past the grace days, suspended, or in human hands: the assistant is
            // silent, so the guard and the audio gate must be too — they spoke
            // into team threads and dropped the message unrecorded (2026-09-24).
            $silent = $business->isSilenced()
                || in_array($conversation->status, [ConversationStatus::Team, ConversationStatus::Customer], true);

            // The plan gate on audio closes BEFORE transcription spends a cent.
            if ($this->audioBase64 !== null && ! $this->audioWithinPlan($business, $plan)) {
                if (! $silent) {
                    $evolution->sendText($this->instance, $this->from, app(TranslateForCustomer::class)->handle($conversation, __('assistant.plan.audio')), 1500);
                }

                $this->rememberInbound($conversation, __('assistant.plan.voice_note_placeholder'));
                $this->handBackToTeam($conversation);
                WhatsAppExchangeArrived::dispatch((int) $business->id, (int) $conversation->id);

                return;
            }

            if ($this->audioBase64 !== null) {
                [$text, $media] = [$this->transcribe($business), []];
            } else {
                $burst = $this->drainBuffer();

                if ($burst === null) {
                    return;
                }

                [$text, $media] = $burst;
            }

            if (($text === null || trim($text) === '') && $media === []) {
                return;
            }

            $media = $this->fileMedia($conversation, $media);
            $mediaText = $this->pdfText($media);
            $body = $this->bodyFor((string) $text, $media);
            $text = $this->promptFor((string) $text, $media, $plan->readsMedia);

            // Read BEFORE the inbound is stored: it must be the first answer after the ask.
            $optInYes = $customer->answersOptInYes($conversation, $body);

            if ($silent) {
                $this->rememberInbound($conversation, $body, $media, $mediaText);

                // The one exception to the silence: a consent yes is sealed and
                // thanked — in a paused business too, where it was lost.
                // A suspended business sends nothing at all, not even the thanks.
                if ($optInYes && ! $business->isSuspended()) {
                    $this->sealOptIn($conversation, $customer, $evolution);
                }

                $this->handBackToTeam($conversation);
                WhatsAppExchangeArrived::dispatch((int) $business->id, (int) $conversation->id);

                return;
            }

            $verdict = app(ConversationGuard::class)->verdict($this->instance, $this->from, $text, $plan->messagesPerHour);

            if ($verdict === GuardVerdict::Muted) {
                return;
            }

            if ($verdict !== GuardVerdict::Ok) {
                $evolution->sendText($this->instance, $this->from, app(TranslateForCustomer::class)->handle($conversation, __('assistant.guard.'.$verdict->value)), 1500);

                return;
            }

            // Best effort, never at the reply's expense: the read receipt and the
            // early "typing…" while the model thinks. No auto-reactions: a thumbs
            // up on a QUESTION reads wrong (owner's call, 2026-09-17).
            rescue(fn () => $evolution->markRead($this->instance, "{$this->from}@s.whatsapp.net", $this->messageId), report: false);
            rescue(fn () => $evolution->markComposing($this->instance, $this->from), report: false);

            // The agent's memory hands over previous turns only: stored first, the
            // current text must not ride twice, so the agent reads it as the prompt.
            $agent = new AsistenteAtendia($business, $conversation, $customer);
            $inbound = $this->rememberInbound($conversation, $body, $media, $mediaText);
            $reply = $agent->withoutMessage($inbound->id)->answer($text, $plan->readsMedia ? $this->attachments($media) : [])->text;

            foreach ($this->bubbles($reply) as $bubble) {
                $evolution->sendText($this->instance, $this->from, $bubble, $this->humanDelay($bubble));
            }

            $this->rememberReply($conversation, $reply, $agent->knowledgeSources());

            // The assistant thanks per its briefing; the seal itself never
            // depends on the model remembering to call its tool.
            if ($optInYes) {
                $customer->sealOptIn();
            }
            $this->rememberForDigest($business, $body, $reply);
            $this->warnOwnerNearCap($business, $evolution);

            WhatsAppExchangeArrived::dispatch((int) $business->id, (int) $conversation->id);
        });
    }

    /** A customer writing into a thread waiting on them hands the ball back: the team owes an answer again. */
    private function handBackToTeam(Conversation $conversation): void
    {
        if ($conversation->status === ConversationStatus::Customer) {
            $conversation->update([
                'status' => ConversationStatus::Team,
                'escalated_at' => now(),
                'handoff_reminded_at' => null,
            ]);
        }
    }

    /**
     * The owner answered the escalation ping right here on WhatsApp: the
     * text rides to the handoff thread they were just handling, through the
     * same action the panel composer uses, and the owner gets a receipt
     * naming who received it. "#resuelto" closes that thread instead, and
     * with no handoff in flight they just get the pointer.
     */
    private function relayOwnerReply(Business $business, EvolutionApi $evolution): void
    {
        if ($this->audioBase64 !== null) {
            $text = $this->transcribe($business);
        } else {
            // The relay carries words only: a photo from the owner stays on their phone.
            [$text, $media] = $this->drainBuffer() ?? [null, []];
            $this->discardMedia($media);
        }

        if ($text === null || trim($text) === '') {
            return;
        }

        app(Tenant::class)->speakingAs($business, function () use ($business, $evolution, $text): void {
            $target = $this->ownerTarget($evolution, $text);

            if ($target === null) {
                return;
            }

            [$thread, $text] = $target;

            if (in_array(mb_strtolower(trim($text)), ['#resuelto', '#resuelta'], true)) {
                $thread->update(['status' => ConversationStatus::Resolved, 'escalated_at' => null, 'handoff_reminded_at' => null]);

                $evolution->sendText($this->instance, $this->from, __('assistant.handoff.resolved_done', [
                    'name' => $thread->contact_name ?? $thread->contact_phone,
                ]));

                WhatsAppExchangeArrived::dispatch((int) $business->id, (int) $thread->id);

                return;
            }

            if (app(SendHumanReply::class)->handle($business, $thread, $text) === null) {
                $this->hintOwnerChannel($evolution);

                return;
            }

            $evolution->sendText($this->instance, $this->from, __('assistant.handoff.relay_done', [
                'name' => $thread->contact_name ?? $thread->contact_phone,
                'url' => route('conversations'),
            ]));

            WhatsAppExchangeArrived::dispatch((int) $business->id, (int) $thread->id);
        });
    }

    /**
     * Which handoff the owner is answering, and the text to send: the ping
     * they QUOTED; else the only one open; else they pick from a numbered
     * list (their text waits until they answer with the number). With two
     * open, "the latest thread" sent answers to the wrong customer.
     *
     * @return array{0: Conversation, 1: string}|null
     */
    private function ownerTarget(EvolutionApi $evolution, string $text): ?array
    {
        // Follow-ups included: a thread already waiting on the customer is
        // still the owner's live handoff, so it stays addressable.
        $open = Conversation::query()
            ->whereIn('status', [ConversationStatus::Team, ConversationStatus::Customer])
            ->orderByDesc('escalated_at')
            ->orderByDesc('last_message_at')
            ->get();

        $quoted = app(OwnerPings::class)->threadFor($this->instance, $this->quotedMessageId);

        if ($quoted !== null && ($thread = $open->firstWhere('id', $quoted)) !== null) {
            return [$thread, $text];
        }

        $pickKey = "wa:pick:{$this->instance}";
        $pending = Cache::get($pickKey);

        if (is_array($pending) && preg_match('/^\s*(\d{1,2})\s*$/', $text, $number) === 1) {
            $thread = $open->firstWhere('id', $pending['ids'][(int) $number[1] - 1] ?? null);

            if ($thread !== null) {
                Cache::forget($pickKey);

                return [$thread, (string) $pending['text']];
            }
        }

        if ($open->isEmpty()) {
            $this->hintOwnerChannel($evolution);

            return null;
        }

        if ($open->count() === 1) {
            return [$open->first(), $text];
        }

        $choices = $open->take(9)->values();
        Cache::put($pickKey, ['text' => $text, 'ids' => $choices->pluck('id')->all()], now()->addMinutes(30));

        $evolution->sendText($this->instance, $this->from, __('assistant.handoff.pick_thread', [
            'list' => $choices->map(fn (Conversation $thread, int $index): string => ($index + 1).') '.($thread->contact_name ?? $thread->contact_phone))->implode("\n"),
        ]));

        return null;
    }

    /**
     * One pointer per hour, not one per text: the owner writing here is
     * usually mid-confusion and a hint avalanche would only add to it.
     */
    private function hintOwnerChannel(EvolutionApi $evolution): void
    {
        if (! Cache::add("wa:ownerhint:{$this->instance}", true, now()->addHour())) {
            return;
        }

        rescue(fn () => $evolution->sendText(
            $this->instance,
            $this->from,
            __('assistant.handoff.owner_channel', ['url' => route('conversations')]),
        ), report: false);
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

        $sentKey = 'wa:cap80:'.$business->id.':'.now($business->localTimezone())->format('Y-m');

        if (! Cache::add($sentKey, true, now()->addDays(45))) {
            return;
        }

        rescue(fn () => $evolution->sendText(
            $this->instance,
            $business->ownerWhatsAppDigits(),
            __('assistant.plan.cap_warning', [
                'used' => $used,
                'cap' => $plan->conversationsPerMonth,
                'url' => route('my-plan'),
            ]),
        ), report: true);
    }

    /**
     * The customer's turn, stored before anything can fail after it.
     *
     * @param  list<array<string, mixed>>  $media
     */
    private function rememberInbound(Conversation $conversation, string $text, array $media = [], ?string $mediaText = null): ConversationMessage
    {
        $inbound = $conversation->messages()->create([
            'direction' => MessageDirection::In,
            'wa_message_id' => $this->messageId,
            'body' => $text,
            'media' => $media === [] ? null : $media,
            'media_text' => $mediaText,
            'audio_seconds' => $this->audioSeconds > 0 ? $this->audioSeconds : null,
        ]);

        $conversation->fill([
            'contact_name' => $this->senderName !== '' ? $this->senderName : $conversation->contact_name,
            'last_message_at' => now(),
        ])->save();

        return $inbound;
    }

    /** @param  list<array{id: int, title: string}>  $sources */
    private function rememberReply(Conversation $conversation, string $reply, array $sources = []): void
    {
        $conversation->messages()->create([
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Assistant,
            'body' => $reply,
            'knowledge_sources' => $sources === [] ? null : $sources,
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();
    }

    /** Seals the consent and says thanks in the business's own voice. */
    private function sealOptIn(Conversation $conversation, Customer $customer, EvolutionApi $evolution): void
    {
        $customer->sealOptIn();

        $thanks = app(TranslateForCustomer::class)->handle($conversation, __('client.customers.opt_in_thanks'));
        $evolution->sendText($this->instance, $this->from, $thanks);

        $conversation->messages()->create([
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Assistant,
            'body' => $thanks,
        ]);
    }

    /**
     * The owner's nightly digest reads from this short-lived tally: cheaper
     * than re-querying the day's threads, and gone by itself after sending.
     */
    private function rememberForDigest(Business $business, string $question, string $reply): void
    {
        // Rolling, not dated: the digest takes everything since the last one.
        $key = 'wa:digest:'.$business->id;
        $entries = (array) Cache::get($key, []);

        if (count($entries) >= 200) {
            return;
        }

        $entries[] = [
            'from' => $this->from,
            'q' => mb_substr($question, 0, 200),
            'a' => mb_substr($reply, 0, 200),
        ];

        Cache::put($key, $entries, now()->addDays(3));
    }

    /**
     * Empties this chat's burst buffer, or refuses the turn: when a newer
     * message re-armed the debounce, ITS job owns the answer and this one
     * dies silently instead of double-replying.
     *
     * @return array{0: string, 1: list<array<string, mixed>>}|null the burst's words and its attachments
     */
    private function drainBuffer(): ?array
    {
        $sender = "{$this->instance}:{$this->from}";
        $last = Cache::get("wa:last:{$sender}");

        if ($last !== null && $last !== $this->messageId) {
            return null;
        }

        $pieces = (array) (Cache::pull("wa:buf:{$sender}") ?? [$this->media ?? $this->text]);
        Cache::forget("wa:last:{$sender}");

        $media = array_values(array_filter($pieces, is_array(...)));
        $texts = array_filter($pieces, fn ($piece): bool => is_string($piece) && trim($piece) !== '');

        return [trim(implode("\n", $texts)), $media];
    }

    /**
     * Moves each parked file into the business's own folder, where the
     * panel serves it re-checking the owner.
     *
     * @param  list<array<string, mixed>>  $media
     * @return list<array<string, mixed>>
     */
    private function fileMedia(Conversation $conversation, array $media): array
    {
        $disk = Storage::disk('local');

        return array_map(function (array $item) use ($conversation, $disk): array {
            $tmp = $item['tmp'] ?? null;
            unset($item['tmp']);
            $item['path'] = null;

            if (is_string($tmp) && $disk->exists($tmp)) {
                $item['path'] = "businesses/{$conversation->business_id}/conversations/{$conversation->id}/".basename($tmp);
                $disk->move($tmp, $item['path']);
            }

            return array_filter($item, fn ($value): bool => $value !== null && $value !== '');
        }, $media);
    }

    /** @param  list<array<string, mixed>>  $media */
    private function discardMedia(array $media): void
    {
        foreach ($media as $item) {
            if (is_string($item['tmp'] ?? null)) {
                Storage::disk('local')->delete($item['tmp']);
            }
        }
    }

    /**
     * What the panel shows and the analysis reads: one line per attachment,
     * its caption under it, then the burst's texts.
     *
     * @param  list<array<string, mixed>>  $media
     */
    private function bodyFor(string $text, array $media): string
    {
        $lines = array_map(fn (array $item): string => trim(ConversationMessage::mediaLine($item)."\n".($item['caption'] ?? '')), $media);

        return trim(implode("\n", [...$lines, $text]));
    }

    /**
     * The same turn for the model, each attachment marked as seen or not:
     * an unseen photo must be asked for in words, never guessed.
     *
     * @param  list<array<string, mixed>>  $media
     */
    private function promptFor(string $text, array $media, bool $readsMedia): string
    {
        $lines = array_map(function (array $item) use ($readsMedia): string {
            $note = match (true) {
                $item['kind'] === 'location' => sprintf(' (coordenadas %s, %s)', $item['lat'] ?? '?', $item['lng'] ?? '?'),
                $readsMedia && $this->readable($item) => ' (adjunto)',
                default => ' (no lo podés ver ni leer)',
            };

            return trim(ConversationMessage::mediaLine($item).$note."\n".($item['caption'] ?? ''));
        }, $media);

        // A bare photo is almost always "do you have this?": said out loud, the
        // model searches the catalog instead of describing the picture back.
        $bare = trim($text) === '' && $media !== []
            && array_filter($media, fn (array $item): bool => ($item['caption'] ?? '') !== '') === []
            && array_filter($media, fn (array $item): bool => $item['kind'] === 'image') !== [];

        if ($bare) {
            $lines[] = '(El cliente mandó solo la foto, sin texto.)';
        }

        return trim(implode("\n", [...$lines, $text]));
    }

    /**
     * The words inside the PDFs, pulled once on arrival so the thread search
     * finds them. Capped: a 300-page catalog must not bloat the row. A scan
     * has no text layer and simply yields nothing.
     *
     * @param  list<array<string, mixed>>  $media
     */
    private function pdfText(array $media): ?string
    {
        $texts = [];

        foreach ($media as $item) {
            if (($item['mime'] ?? '') !== 'application/pdf' || ! isset($item['path'])
                || Storage::disk('local')->size($item['path']) > self::PDF_MAX_BYTES) {
                continue;
            }

            $texts[] = (string) rescue(
                fn (): string => (new PdfParser)->parseContent((string) Storage::disk('local')->get($item['path']))->getText(),
                '',
                report: false,
            );
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', implode(' ', $texts)));

        return $text === '' ? null : mb_substr($text, 0, 20000);
    }

    /**
     * Photos and PDFs the model can take; a Word file or an oversized PDF
     * waits for the team.
     *
     * @param  array<string, mixed>  $item
     */
    private function readable(array $item): bool
    {
        if (! isset($item['path'])) {
            return false;
        }

        return match ($item['kind']) {
            'image' => in_array($item['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true),
            'document' => ($item['mime'] ?? '') === 'application/pdf' && Storage::disk('local')->size($item['path']) <= self::PDF_MAX_BYTES,
            default => false,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $media
     * @return list<File>
     */
    private function attachments(array $media): array
    {
        return array_values(array_map(
            fn (array $item): File => $item['kind'] === 'image' ? Image::fromStorage($item['path'], 'local') : Document::fromStorage($item['path'], 'local'),
            array_filter($media, $this->readable(...)),
        ));
    }

    /** Audio rides only while the plan includes it AND the month's minutes last. */
    private function audioWithinPlan(Business $business, Plan $plan): bool
    {
        if (! $plan->allowsAudio) {
            return false;
        }

        return $business->audioSecondsThisMonth() < $plan->audioMinutesPerMonth * 60;
    }

    /**
     * Voice notes become text and join the same pipeline; replies stay text.
     * Inside the tenant: the usage meter stamps it on this business, not on nobody.
     */
    private function transcribe(Business $business): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'wa-audio-');

        try {
            file_put_contents($path, base64_decode((string) $this->audioBase64, true) ?: '');

            return (string) app(Tenant::class)->for((int) $business->id, fn () => Transcription::fromPath($path)->generate());
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
