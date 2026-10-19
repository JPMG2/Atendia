<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * The owner's end-of-day read, built once and printed twice: as the closing
 * mail and as the WhatsApp answer to "resumen". Both say the same figures.
 */
class DailyDigestDto
{
    /**
     * @param  list<string>  $questions  what customers asked, most recent first, no repeats
     */
    public function __construct(
        public readonly string $business,
        public readonly int $messages,
        public readonly int $contacts,
        public readonly string $summary = '',
        public readonly array $questions = [],
        public readonly int $referred = 0,
        public readonly int $appointmentsTomorrow = 0,
    ) {}

    /** The plain WhatsApp rendering of the same read. */
    public function toText(): string
    {
        $text = __('assistant.digest.header', [
            'business' => $this->business,
            'messages' => $this->messages,
            'contacts' => $this->contacts,
        ]);

        if ($this->summary !== '') {
            $text .= "\n\n".$this->summary;
        }

        if ($this->referred > 0) {
            $text .= "\n\n".trans_choice('assistant.digest.referrals', $this->referred, ['count' => $this->referred]);
        }

        return $text;
    }
}
