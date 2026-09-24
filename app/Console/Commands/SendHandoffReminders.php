<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Business\TranslateForCustomer;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Models\Business;
use App\Models\Conversation;
use App\Services\EvolutionApi;
use App\Services\OwnerPings;
use App\Services\Tenant;
use Illuminate\Console\Command;

/**
 * The forgotten-thread net (the owner's worry, 2026-09-20): a thread left
 * waiting for the team past the grace window re-pings the owner and tells
 * the customer, ONCE per escalation, that a person is on the way. The
 * assistant never resolves over the human's head.
 */
class SendHandoffReminders extends Command
{
    protected $signature = 'atendia:handoff-reminders';

    protected $description = 'Remind owner and customer about threads still waiting for the team';

    public function handle(EvolutionApi $evolution): int
    {
        $this->remindForgotten($evolution);
        $this->autoResume($evolution);
        $this->resolveIdleCustomerThreads();

        return self::SUCCESS;
    }

    /**
     * A thread waiting on a customer who went quiet closes itself as
     * resolved: the team answered, nobody replied — that IS a resolution,
     * and the assistant takes that customer's NEXT question from scratch.
     * Silent on purpose: there is nobody left to message.
     */
    private function resolveIdleCustomerThreads(): void
    {
        $hours = (int) config('atendia.handoff.customer_idle_hours');

        if ($hours <= 0) {
            return;
        }

        $idle = Conversation::query()
            ->where('status', ConversationStatus::Customer)
            ->where('last_message_at', '<=', now()->subHours($hours))
            ->get();

        foreach ($idle as $thread) {
            $thread->forceFill([
                'status' => ConversationStatus::Resolved,
                'escalated_at' => null,
                'handoff_reminded_at' => null,
            ])->save();

            $this->info("Idle-resolved {$thread->contact_phone}");
        }
    }

    private function remindForgotten(EvolutionApi $evolution): void
    {
        $floor = now()->subMinutes((int) config('atendia.handoff.reminder_minutes'));

        $forgotten = Conversation::query()
            ->with('business')
            ->where('status', ConversationStatus::Team)
            ->whereNull('handoff_reminded_at')
            ->whereNotNull('escalated_at')
            ->where('escalated_at', '<=', $floor)
            ->get();

        foreach ($forgotten as $thread) {
            $business = $thread->business;

            if ($business === null || ! $business->isConnected() || $business->whatsapp_instance === null) {
                continue;
            }

            // Quiet hours: "a person is on the way" is a promise — it only
            // goes out while the business is actually open.
            if (! $business->isOpenNow()) {
                continue;
            }

            $minutes = (int) $thread->escalated_at->diffInMinutes(now());

            // One thread failing (a bad owner number, Evolution down) never
            // blocks the rest, and never re-sends "on the way" every 10 minutes.
            $sent = rescue(fn (): bool => app(Tenant::class)->for((int) $business->id, function () use ($evolution, $business, $thread, $minutes): bool {
                // The customer first, in THEIR language: that wait costs trust.
                $this->tellCustomer($evolution, $business, $thread, __('assistant.handoff.hold_customer', ['business' => $business->name]));

                // Stamped right after the customer's line: a failing owner ping below must not repeat it.
                $thread->forceFill(['handoff_reminded_at' => now()])->save();

                $owner = $business->ownerWhatsAppDigits();

                if ($owner !== '') {
                    $pingId = $evolution->sendText(
                        $business->whatsapp_instance,
                        $owner,
                        __('assistant.handoff.reminder_owner', [
                            'name' => $thread->contact_name ?? $thread->contact_phone,
                            'minutes' => $minutes,
                        ]),
                    );

                    app(OwnerPings::class)->remember((string) $business->whatsapp_instance, $pingId, (int) $thread->id);
                }

                return true;
            }), false);

            if (! $sent) {
                continue;
            }

            $this->info("Reminded {$business->name} about {$thread->contact_phone} ({$minutes} min)");
        }
    }

    /**
     * The optional last net: after N hours with no human word, the assistant
     * takes the thread back so the customer is never abandoned. Off unless
     * the owner sets the hours.
     */
    private function autoResume(EvolutionApi $evolution): void
    {
        $hours = config('atendia.handoff.auto_resume_hours');

        if ($hours === null) {
            return;
        }

        $abandoned = Conversation::query()
            ->with('business')
            ->where('status', ConversationStatus::Team)
            ->whereNotNull('handoff_reminded_at')
            ->whereNotNull('escalated_at')
            ->where('escalated_at', '<=', now()->subHours((int) $hours))
            ->get();

        foreach ($abandoned as $thread) {
            $business = $thread->business;

            if ($business === null || ! $business->isConnected() || $business->whatsapp_instance === null) {
                continue;
            }

            $resumed = rescue(fn (): bool => app(Tenant::class)->for((int) $business->id, function () use ($evolution, $business, $thread): bool {
                $this->tellCustomer($evolution, $business, $thread, __('assistant.handoff.auto_resume'));

                $thread->forceFill([
                    'status' => ConversationStatus::Open,
                    'escalated_at' => null,
                    'handoff_reminded_at' => null,
                ])->save();

                return true;
            }), false);

            if (! $resumed) {
                continue;
            }

            $this->info("Auto-resumed {$thread->contact_phone} for {$business->name}");
        }
    }

    /**
     * Courtesy in the customer's tongue, written into the thread too: a reply
     * to it ("gracias") needs the assistant to know what was said. The thread
     * keeps its last_message_at: an automated line is not activity.
     */
    private function tellCustomer(EvolutionApi $evolution, Business $business, Conversation $thread, string $text): void
    {
        $text = app(TranslateForCustomer::class)->handle($thread, $text);

        $evolution->sendText((string) $business->whatsapp_instance, $thread->contact_phone, $text);

        $thread->messages()->create([
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Assistant,
            'body' => $text,
        ]);
    }
}
