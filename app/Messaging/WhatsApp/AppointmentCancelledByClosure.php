<?php

declare(strict_types=1);

namespace App\Messaging\WhatsApp;

use App\Messaging\WhatsAppMessage;
use Illuminate\Database\Eloquent\Model;

/**
 * The business will not open that day after all, so the hour someone was
 * holding is gone. Said with the business's name and the hour the person is
 * expecting: "tu turno se canceló" on its own is a riddle.
 */
class AppointmentCancelledByClosure extends WhatsAppMessage
{
    public function __construct(Model $model, private readonly ?string $reason = null)
    {
        parent::__construct($model);
    }

    public function text(): string
    {
        $business = $this->model->business;
        $when = $this->model->starts_at
            ->setTimezone($business->localTimezone())
            ->translatedFormat('d/m/Y \a \l\a\s H:i');

        return $this->reason === null
            ? __('agenda.closure_notice', ['business' => $business->name, 'when' => $when])
            : __('agenda.closure_notice_reason', ['business' => $business->name, 'when' => $when, 'reason' => $this->reason]);
    }
}
