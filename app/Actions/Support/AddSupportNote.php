<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;

/**
 * What a person found, left for whoever picks the report up next. It goes
 * nowhere: no delivery, no change of status, and the business never reads it.
 */
class AddSupportNote
{
    public function handle(SupportTicket $ticket, string $note, User $author): SupportTicketMessage
    {
        return SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'business_id' => $ticket->business_id,
            'user_id' => $author->id,
            'body' => trim($note),
            'is_internal' => true,
            'delivery' => null,
        ]);
    }
}
