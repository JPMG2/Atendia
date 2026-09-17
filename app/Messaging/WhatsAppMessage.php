<?php

declare(strict_types=1);

namespace App\Messaging;

use Illuminate\Database\Eloquent\Model;

/**
 * What the WhatsApp channel demands of a message: plain text built from the
 * record it talks about. The Mailable analog for this medium — a system
 * message declares its wording here, never inline where it is sent.
 */
abstract class WhatsAppMessage
{
    public function __construct(public Model $model) {}

    /** The body to deliver, built under the locale the ritual captured. */
    abstract public function text(): string;
}
