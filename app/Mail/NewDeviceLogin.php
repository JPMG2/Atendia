<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * The security notice a user gets when their account signs in from a device
 * never seen before. One message, one decision: "was this you?" — with the
 * password reset one click away for when the answer is no.
 */
class NewDeviceLogin extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The message knows WHAT to say; who reads it is the channel's business,
     * so only the model comes in. Public so the views read `$model` directly.
     */
    public function __construct(
        public Model $model,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.new_device.subject'),
        );
    }

    /**
     * The "this wasn't me" link is signed and lives a week: long enough for
     * a mail read late, short enough that a leaked inbox is not a master key.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.security.new-device',
            text: 'emails.security.new-device-text',
            with: [
                'revokeUrl' => URL::temporarySignedRoute(
                    'security.devices.revoke',
                    now()->addDays(7),
                    ['device' => $this->model->id],
                ),
            ],
        );
    }
}
