<?php

declare(strict_types=1);

namespace App\Mail;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Security receipt for the lost-phone pause, both ways. The resume one is
 * the one that matters: if she did not lift it, the reset link is right there.
 */
class LostPhonePauseChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{name: string, device: string, app: bool, current?: bool, last_login_at: string|null}>  $cut  What the pause cut, as the card lists it.
     */
    public function __construct(
        public Model $model,
        public bool $paused,
        public array $cut = [],
        public bool $byStaff = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.account.lost_phone.'.$this->key().'.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account.lost-phone',
            text: 'emails.account.lost-phone-text',
            with: [
                'key' => 'mail.account.lost_phone.'.$this->key(),
                'time' => now()->translatedFormat('d \d\e F \d\e Y, H:i'),
                'cutLines' => $this->cutLines(),
                // Paused by staff, her password is one nobody knows: the door is choosing a new one.
                'ctaUrl' => $this->paused && ! $this->byStaff ? route('whatsapp') : route('password.request'),
            ],
        );
    }

    /**
     * One line per device, in the card's words, so she can spot the stolen phone from her inbox.
     *
     * @return list<string>
     */
    private function cutLines(): array
    {
        $zone = $this->model->business?->localTimezone() ?? config('app.timezone');

        return array_map(fn (array $cut): string => implode(' · ', array_filter([
            $cut['name'],
            $cut['device'],
            $cut['app'] ? __('whatsapp.lost_phone.cut_app') : null,
            ($cut['current'] ?? false) ? __('whatsapp.lost_phone.cut_current') : null,
            $cut['last_login_at'] === null ? null : __('whatsapp.lost_phone.cut_last').' '.CarbonImmutable::parse($cut['last_login_at'])->setTimezone($zone)->format('d/m/Y H:i'),
        ])), $this->cut);
    }

    private function key(): string
    {
        return match (true) {
            ! $this->paused => 'resumed',
            $this->byStaff => 'paused_by_staff',
            default => 'paused',
        };
    }
}
