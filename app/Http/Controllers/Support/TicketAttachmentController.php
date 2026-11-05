<?php

declare(strict_types=1);

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a ticket's screenshot from the PRIVATE disk. Only the support route
 * reaches it, behind `support.view`: the picture can show a business's own
 * customers, so it never has a public, guessable URL.
 */
class TicketAttachmentController extends Controller
{
    public function __invoke(SupportTicket $ticket): StreamedResponse
    {
        abort_if($ticket->attachment_path === null || ! Storage::disk('local')->exists($ticket->attachment_path), 404);

        return Storage::disk('local')->response($ticket->attachment_path, null, ['X-Content-Type-Options' => 'nosniff']);
    }
}
