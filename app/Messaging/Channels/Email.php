<?php

declare(strict_types=1);

namespace App\Messaging\Channels;

use App\Enums\MailSender;
use App\Messaging\Channel;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class Email extends Channel
{
    /** Only a Mailable goes out this way: anything else is refused on construction. */
    protected const MESSAGE_CONTRACT = Mailable::class;

    /**
     * The recipient is set HERE and not by the message: the same mail has to be
     * able to reach anyone, and the address lives in one place. The sender is
     * decided here too, by what the mail is for. Since the Mailable is
     * ShouldQueue, this hands the send to the queue and returns.
     */
    protected function deliver(string $locale): void
    {
        $sender = MailSender::for($this->message);

        $mail = (new ($this->message)($this->model, ...$this->messageArguments))
            ->from($sender->address(), $sender->displayName($locale));

        Mail::to($this->receives)
            ->locale($locale)
            ->send($mail);
    }
}
