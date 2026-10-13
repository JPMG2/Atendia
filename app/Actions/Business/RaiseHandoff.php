<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Enums\ConversationStatus;
use App\Messaging\Channels\Panel;
use App\Messaging\Panel\HandedToTeam;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Department;
use App\Services\EvolutionApi;
use App\Services\OwnerPings;
use Illuminate\Support\Collection;

/**
 * Hands ONE thread to the team and alerts the people who can take it.
 * The assistant's tool and the job's own safety rules both land here, so
 * a handoff means the same thing whoever raised it.
 */
class RaiseHandoff
{
    public function handle(Business $business, Conversation $conversation, string $reason, ?Department $department = null): void
    {
        $conversation->update([
            'status' => ConversationStatus::Team,
            'escalated_at' => now(),
            'handoff_reminded_at' => null,
            'department_id' => $department?->id,
        ]);

        // The panel reads the same news as the pings, and keeps it: a WhatsApp
        // alert sent at 3am is gone by morning, the bell row is still there.
        (new Panel($conversation, [], HandedToTeam::class))->send();

        // Best effort: a failed ping must never break the escalation itself.
        rescue(fn () => $this->alertTeam($business, $conversation, $reason, $department));
    }

    private function alertTeam(Business $business, Conversation $conversation, string $reason, ?Department $department): void
    {
        if ($business->whatsapp_instance === null
            || $business->isOwnerWhatsApp((string) $conversation->contact_phone)) {
            return;
        }

        $members = $department?->reachableMembers() ?? new Collection;
        $details = [
            'name' => $conversation->contact_name ?? $conversation->contact_phone,
            'phone' => $conversation->contact_phone,
            'reason' => $reason !== '' ? $reason : __('assistant.handoff.no_reason'),
            // The alert names ONE customer and says "atiéndelo": the link opens
            // that thread, not an inbox where the name has to be found again.
            'url' => route('conversations', ['hilo' => $conversation->id]),
        ];

        // Nobody available in the room: the owner, as before departments existed.
        if ($members->isEmpty()) {
            $this->ping($business, $conversation, $business->ownerWhatsAppDigits(), __('assistant.handoff.owner_alert', $details));

            return;
        }

        foreach ($members as $member) {
            $this->ping($business, $conversation, $member->whatsappDigits(), __('assistant.handoff.department_alert', [...$details, 'department' => $department->name]));
        }
    }

    private function ping(Business $business, Conversation $conversation, string $number, string $text): void
    {
        if ($number === '') {
            return;
        }

        $pingId = app(EvolutionApi::class)->sendText($business->whatsapp_instance, $number, $text);

        app(OwnerPings::class)->remember($business->whatsapp_instance, $pingId, (int) $conversation->id);
    }
}
