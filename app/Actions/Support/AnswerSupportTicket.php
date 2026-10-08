<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\SupportDelivery;
use App\Enums\SupportTicketStatus;
use App\Mail\SupportTicketAnswered as SupportTicketAnsweredMail;
use App\Messaging\Channels\Email;
use App\Messaging\Channels\Panel;
use App\Messaging\Channels\WhatsApp;
use App\Messaging\Panel\SupportTicketResolved;
use App\Messaging\WhatsApp\SupportTicketAnswered;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Tenant;
use Throwable;

/**
 * Our side of the conversation. An answer is written down first and sent
 * second, and the screen is told HOW it left: an answer that reached nobody
 * must not read as an answered report, because the person who wrote it moves
 * on to the next one believing the customer was attended.
 */
class AnswerSupportTicket
{
    /**
     * Writes the answer, sends it, and says how it left. The ball changes sides
     * (the status moves to Waiting) only when the business actually has it.
     *
     * @param  bool  $movesStatus  False for an answer given while blocking the report:
     *                             the status is Blocked there, whatever the delivery says.
     */
    public function reply(SupportTicket $ticket, string $reply, ?User $author = null, bool $movesStatus = true): SupportDelivery
    {
        $text = trim($reply);

        $delivery = $this->send($ticket, $text);

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'business_id' => $ticket->business_id,
            'user_id' => $author?->id,
            'body' => $text,
            'is_internal' => false,
            'delivery' => $delivery,
        ]);

        if ($delivery->reached()) {
            $ticket->update([
                'reply' => $text,
                'answered_at' => now(),
                ...($movesStatus ? ['status' => SupportTicketStatus::Waiting] : []),
            ]);
        }

        // Whoever answers first has it: two people never reply to the same report.
        if ($ticket->assigned_to === null && $author !== null) {
            $ticket->update(['assigned_to' => $author->id]);
        }

        return $delivery;
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

    /**
     * WhatsApp first, because that is where the owner already answers; the
     * mail takes over when there is no number or the send failed. Nothing to
     * reach them by, or both failing, is said as such and never swallowed.
     */
    private function send(SupportTicket $ticket, string $text): SupportDelivery
    {
        $business = $ticket->business;
        $number = $business?->ownerWhatsAppDigits();

        if ($number !== null && $number !== '') {
            // The message reads the text off the ticket; it is not saved until it
            // left, so the attribute goes back to what it was either way.
            $ticket->setAttribute('reply', $text);
            $sent = new WhatsApp($ticket, [$number], SupportTicketAnswered::class)->send();
            $ticket->setAttribute('reply', $ticket->getOriginal('reply'));
            $ticket->syncOriginalAttribute('reply');

            if ($sent) {
                return SupportDelivery::WhatsApp;
            }
        }

        $address = $ticket->user?->email ?: ($business?->billing_email ?: $business?->email);

        if ($address === null || $address === '') {
            return $number !== null && $number !== '' ? SupportDelivery::Failed : SupportDelivery::NoContact;
        }

        return new Email($ticket, [$address], SupportTicketAnsweredMail::class, [$text])->send()
            ? SupportDelivery::Email
            : SupportDelivery::Failed;
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
