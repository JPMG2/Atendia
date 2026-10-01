<?php

declare(strict_types=1);

namespace App\Messaging\WhatsApp;

use App\Messaging\WhatsAppMessage;

/**
 * Our answer, where she already is. A reply that only lives in a panel she has
 * no reason to reopen is an answer nobody reads.
 */
class SupportTicketAnswered extends WhatsAppMessage
{
    public function text(): string
    {
        return __('support.whatsapp_answer', [
            'code' => $this->model->code,
            'reply' => $this->model->reply,
        ]);
    }
}
