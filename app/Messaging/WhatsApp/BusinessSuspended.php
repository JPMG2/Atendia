<?php

declare(strict_types=1);

namespace App\Messaging\WhatsApp;

use App\Messaging\WhatsAppMessage;

/** The same notice as the mail, in one line to the owner's own phone. */
class BusinessSuspended extends WhatsAppMessage
{
    public function text(): string
    {
        return __('moderation.whatsapp', ['name' => $this->model->name]);
    }
}
