<?php

declare(strict_types=1);

namespace App\Messaging\Channels;

use App\Messaging\Channel;
use App\Messaging\PanelMessage;
use App\Models\Business;
use App\Models\PanelNotification;
use RuntimeException;

class Panel extends Channel
{
    /** Only a PanelMessage goes out this way: anything else is refused on construction. */
    protected const MESSAGE_CONTRACT = PanelMessage::class;

    /**
     * `$receives` stays empty here, and that is the honest shape: the business's
     * own panel is the address. The notice is stored ONCE and every member of
     * the team reads it, each with their own read mark.
     *
     * The captured locale is ignored on purpose — see PanelMessage: the row is
     * worded by whoever opens the panel, not by whoever raised it.
     */
    protected function deliver(string $locale): void
    {
        $message = new $this->message($this->model, ...$this->messageArguments);

        PanelNotification::raise(
            $this->business(),
            $message->type(),
            $message->dedupeKey(),
            $message->payload(),
            $message->url(),
            $message->revives(),
        );
    }

    /**
     * Whose panel it lands on. A message talks about a record, and every record
     * that talks either IS the business or belongs to one.
     */
    private function business(): Business
    {
        $business = $this->model instanceof Business ? $this->model : $this->model->getAttribute('business');

        if (! $business instanceof Business) {
            throw new RuntimeException(sprintf('[%s] %s has no business to notify.', static::class, $this->model::class));
        }

        return $business;
    }
}
