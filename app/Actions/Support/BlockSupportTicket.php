<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\SupportDelivery;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * A report that could not be solved today, left so the next person knows
 * exactly what stands in the way: what was tried, what is missing, who follows
 * and by when. Closing it without that is how a problem gets lost twice.
 */
class BlockSupportTicket
{
    public function __construct(private readonly AnswerSupportTicket $answers) {}

    /**
     * Blocks it, and tells the business what happens next when there is
     * something to tell. The status is Blocked whatever the message's
     * delivery was: the block is a fact about the case, not about the notice.
     *
     * @return SupportDelivery|null How the notice left, null when none was written.
     */
    public function handle(
        SupportTicket $ticket,
        string $tried,
        string $missing,
        string $owner,
        CarbonInterface $due,
        ?string $toTheBusiness,
        User $author,
    ): ?SupportDelivery {
        $ticket->update([
            'status' => SupportTicketStatus::Blocked,
            'blocked_tried' => trim($tried),
            'blocked_missing' => trim($missing),
            'blocked_owner' => trim($owner),
            'blocked_due' => $due->toDateString(),
            'blocked_at' => now(),
            'assigned_to' => $ticket->assigned_to ?? $author->id,
        ]);

        $notice = trim((string) $toTheBusiness);

        return $notice === '' ? null : $this->answers->reply($ticket, $notice, $author, movesStatus: false);
    }
}
