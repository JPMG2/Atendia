<?php

declare(strict_types=1);

namespace App\Messaging\Channels;

use App\Messaging\Channel;
use App\Messaging\WhatsAppMessage;
use App\Services\EvolutionApi;

class WhatsApp extends Channel
{
    /** Only a WhatsAppMessage goes out this way: anything else is refused on construction. */
    protected const MESSAGE_CONTRACT = WhatsAppMessage::class;

    /**
     * Recipients are phone numbers in international digits. The text is built
     * under the captured locale set/restored by hand — the HTTP client has no
     * locale() the way Mail does — so `__()` inside the message resolves to
     * the variant the person picked, not the worker's fallback.
     */
    protected function deliver(string $locale): void
    {
        $original = app()->getLocale();
        app()->setLocale($locale);

        try {
            $text = (new $this->message($this->model))->text();
        } finally {
            app()->setLocale($original);
        }

        $instance = (string) config('services.evolution.instance');

        foreach ($this->receives as $number) {
            app(EvolutionApi::class)->sendText($instance, $number, $text);
        }
    }
}
