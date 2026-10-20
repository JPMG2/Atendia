<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Support\SuggestForSupportTicket;
use App\Models\SupportTicket;
use App\Services\Tenant;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Asks for the AI's reading of a new report once it is saved, off the request
 * that filed it: the person who reported must never wait for a model.
 */
class PrepareSupportSuggestion implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Under the queue's retry window: above it a slow run is handed to a second worker and paid twice. */
    public int $timeout = 60;

    /** A second try covers a provider hiccup; a third would only pay again for the same answer. */
    public int $tries = 2;

    public function __construct(public int $businessId, public int $ticketId) {}

    public function uniqueId(): string
    {
        return (string) $this->ticketId;
    }

    public function handle(SuggestForSupportTicket $suggest): void
    {
        app(Tenant::class)->for($this->businessId, function () use ($suggest): void {
            $ticket = SupportTicket::query()->with('business')->find($this->ticketId);

            if ($ticket !== null && $ticket->business !== null) {
                $suggest->handle($ticket);
            }
        });
    }
}
