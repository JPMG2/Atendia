<?php

declare(strict_types=1);

namespace App\Messaging\Panel;

use App\Enums\PanelNotificationType;
use App\Messaging\PanelMessage;

/** A slot the assistant just took: the only row in this inbox that is good news. */
class AppointmentBooked extends PanelMessage
{
    public function type(): PanelNotificationType
    {
        return PanelNotificationType::AppointmentBooked;
    }

    public function dedupeKey(): string
    {
        return 'booking:'.$this->model->getKey();
    }

    /**
     * @return array<string, string|int>
     */
    public function payload(): array
    {
        $appointment = $this->model->loadMissing(['customer', 'service', 'business']);

        return [
            'name' => $appointment->customer?->name ?? '',
            'when' => $appointment->starts_at
                ->copy()
                ->setTimezone($appointment->business->localTimezone())
                ->format('d/m H:i'),
            'service' => $appointment->service?->name ?? '',
        ];
    }

    public function url(): ?string
    {
        return route('agenda');
    }
}
