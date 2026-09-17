<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * One inbound WhatsApp text, already parsed and acknowledged. Queued so the
 * webhook answers Evolution instantly: the reply pipeline that grows here
 * (assistant + RAG) takes seconds the webhook must not hold.
 */
class ProcessIncomingWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $instance,
        public string $from,
        public string $senderName,
        public string $text,
        public string $messageId,
    ) {}

    public function handle(): void
    {
        // Reception slice: land the message and leave a trace. The assistant
        // reply hooks in here on the next slice.
        Log::info('whatsapp.incoming', [
            'instance' => $this->instance,
            'from' => $this->from,
            'name' => $this->senderName,
            'text' => $this->text,
            'message_id' => $this->messageId,
        ]);
    }
}
