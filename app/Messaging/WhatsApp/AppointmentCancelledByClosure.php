<?php

declare(strict_types=1);

namespace App\Messaging\WhatsApp;

use App\Messaging\WhatsAppMessage;
use App\Services\ProactiveSends;
use Illuminate\Database\Eloquent\Model;

/**
 * The business will not open that day after all, so the hour someone was
 * holding is gone. Said with the business's name and the hour the person is
 * expecting: "tu turno se canceló" on its own is a riddle. A person holding
 * several hours in the closure hears about all of them in ONE message.
 */
class AppointmentCancelledByClosure extends WhatsAppMessage
{
    /**
     * @param  list<Model>  $also  The same person's other bookings inside the closure.
     */
    public function __construct(Model $model, private readonly ?string $reason = null, private readonly array $also = [])
    {
        parent::__construct($model);
    }

    public function text(): string
    {
        $business = $this->model->business;
        $when = collect([$this->model, ...$this->also])
            ->sortBy('starts_at')
            ->map(fn (Model $booking): string => $booking->starts_at->setTimezone($business->localTimezone())->translatedFormat('d/m/Y \a \l\a\s H:i'))
            ->join(', del ', ' y del ');

        // One of several wordings, picked by the booking: a retry says the same words, the batch does not.
        return $this->reason === null
            ? ProactiveSends::wording('agenda.closure_notice', $this->model->id, ['business' => $business->name, 'when' => $when])
            : ProactiveSends::wording('agenda.closure_notice_reason', $this->model->id, ['business' => $business->name, 'when' => $when, 'reason' => $this->reason]);
    }
}
