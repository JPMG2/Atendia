<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Agents\ReplyTranslator;
use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Services\EvolutionApi;
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

        return self::SUCCESS;
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

            // The customer first, in THEIR language: that wait costs trust.
            $evolution->sendText(
                $business->whatsapp_instance,
                $thread->contact_phone,
                $this->inCustomerLanguage($thread, __('assistant.handoff.hold_customer', ['business' => $business->name])),
            );

            $owner = $business->ownerWhatsAppDigits();

            if ($owner !== '') {
                $evolution->sendText(
                    $business->whatsapp_instance,
                    $owner,
                    __('assistant.handoff.reminder_owner', [
                        'name' => $thread->contact_name ?? $thread->contact_phone,
                        'minutes' => $minutes,
                    ]),
                );
            }

            $thread->forceFill(['handoff_reminded_at' => now()])->save();

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

            $evolution->sendText(
                $business->whatsapp_instance,
                $thread->contact_phone,
                $this->inCustomerLanguage($thread, __('assistant.handoff.auto_resume')),
            );

            $thread->forceFill([
                'status' => ConversationStatus::Open,
                'escalated_at' => null,
                'handoff_reminded_at' => null,
            ])->save();

            $this->info("Auto-resumed {$thread->contact_phone} for {$business->name}");
        }
    }

    /** Courtesy in the customer's tongue; a failed translation ships Spanish. */
    private function inCustomerLanguage(Conversation $thread, string $text): string
    {
        $language = strtolower((string) $thread->language);

        if ($language === '' || str_starts_with($language, 'es')) {
            return $text;
        }

        return rescue(function () use ($language, $text): string {
            $response = new ReplyTranslator()->prompt(
                "Idioma del cliente: {$language}\n\nMensaje (en español):\n{$text}",
            );

            $translated = trim((string) ($response['text'] ?? ''));

            return $translated !== '' ? $translated : $text;
        }, $text, report: false);
    }
}
