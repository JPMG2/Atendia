<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\SupportTicketKind;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Mail\SupportTicketOpened as SupportTicketOpenedMail;
use App\Messaging\Channels\Email;
use App\Messaging\Channels\WhatsApp;
use App\Messaging\WhatsApp\SupportTicketOpened as SupportTicketOpenedMessage;
use App\Models\Company;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Writes the report and tells the team, by mail and by WhatsApp. The notice
 * never takes the ticket down with it: a report that was saved but not
 * announced is a problem for us, while a report lost is a problem for her.
 */
class CreateSupportTicket
{
    /** The code is random, so a clash is a retry, not an error. */
    private const int CODE_ATTEMPTS = 5;

    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(
        User $user,
        string $body,
        SupportTicketKind $kind,
        ?string $screen,
        array $context,
        mixed $attachment = null,
        bool $afterHelp = false,
    ): SupportTicket {
        $path = $attachment instanceof UploadedFile
            ? $attachment->store('businesses/'.$user->business_id.'/support', 'public')
            : null;

        try {
            $ticket = $this->write($user, $body, $kind, $screen, $context, $path, $afterHelp);
        } catch (Throwable $e) {
            // The file is useless without its row, and storage is not free.
            if ($path !== null) {
                Storage::disk('public')->delete($path);
            }

            throw $e;
        }

        $this->tellTheTeam($ticket);

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function write(
        User $user,
        string $body,
        SupportTicketKind $kind,
        ?string $screen,
        array $context,
        ?string $path,
        bool $afterHelp,
    ): SupportTicket {
        $attempt = 0;

        while (true) {
            try {
                return SupportTicket::query()->create([
                    'business_id' => $user->business_id,
                    'user_id' => $user->id,
                    'code' => SupportTicket::freshCode(),
                    'kind' => $kind,
                    'status' => SupportTicketStatus::New,
                    'priority' => SupportTicketPriority::startingFor($kind),
                    'screen' => $screen,
                    'after_help' => $afterHelp,
                    'body' => $body,
                    'attachment_path' => $path,
                    'context' => $context,
                ]);
            } catch (QueryException $e) {
                // Only the code is unique here; anything else is a real fault.
                if (++$attempt >= self::CODE_ATTEMPTS || ! str_contains($e->getMessage(), 'code')) {
                    throw $e;
                }
            }
        }
    }

    /** Both ways, as asked, and neither can break the other or the ticket. */
    private function tellTheTeam(SupportTicket $ticket): void
    {
        $email = (string) config('atendia.admin_email');

        if ($email !== '') {
            try {
                new Email($ticket, [$email], SupportTicketOpenedMail::class)->send();
            } catch (Throwable $e) {
                report($e);
            }
        }

        $whatsapp = (string) Company::whatsapp();

        if ($whatsapp !== '') {
            try {
                new WhatsApp($ticket, [$whatsapp], SupportTicketOpenedMessage::class)->send();
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
