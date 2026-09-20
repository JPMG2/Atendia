<?php

declare(strict_types=1);

namespace App\Console\Commands;

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

            $minutes = (int) $thread->escalated_at->diffInMinutes(now());

            // The customer first: their wait is the one that costs trust.
            $evolution->sendText(
                $business->whatsapp_instance,
                $thread->contact_phone,
                __('assistant.handoff.hold_customer', ['business' => $business->name]),
            );

            $owner = (string) preg_replace('/\D/', '', (string) $business->fallback_whatsapp_number);

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

        return self::SUCCESS;
    }
}
