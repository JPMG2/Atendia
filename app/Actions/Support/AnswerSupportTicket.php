<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\SupportTicketStatus;
use App\Messaging\Channels\Panel;
use App\Messaging\Channels\WhatsApp;
use App\Messaging\Panel\SupportTicketResolved;
use App\Messaging\WhatsApp\SupportTicketAnswered;
use App\Models\SupportTicket;
use App\Services\Tenant;
use Throwable;

/**
 * Our side of the conversation: the reply travels to where she already is, and
 * closing one tells her so through the bell she already reads. Neither notice
 * may undo the write — a status that moved only in our heads is worse than one
 * she did not hear about.
 */
class AnswerSupportTicket
{
    /**
     * Writes the reply and sends it. The status moves to Waiting, which is the
     * truth: the ball is hers now.
     */
    public function reply(SupportTicket $ticket, string $reply): SupportTicket
    {
        $ticket->update([
            'reply' => trim($reply),
            'answered_at' => now(),
            'status' => SupportTicketStatus::Waiting,
        ]);

        $number = $ticket->business?->ownerWhatsAppDigits();

        if ($number !== null && $number !== '') {
            $this->quietly(fn () => new WhatsApp($ticket, [$number], SupportTicketAnswered::class)->send());
        }

        return $ticket;
    }

    /** Closing it rings her bell, worded when she opens the panel. */
    public function resolve(SupportTicket $ticket): SupportTicket
    {
        $ticket->update([
            'status' => SupportTicketStatus::Resolved,
            'resolved_at' => now(),
        ]);

        // The admin has no tenant of her own, and the bell row belongs to the
        // business: without adopting it the notification lands nowhere.
        $this->quietly(fn () => app(Tenant::class)->for(
            (int) $ticket->business_id,
            fn () => new Panel($ticket, [], SupportTicketResolved::class)->send(),
        ));

        return $ticket;
    }

    private function quietly(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
