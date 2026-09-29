<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Agenda\BookAppointment;
use App\Ai\Agents\AsistenteAtendia;
use App\Classes\Main\AssistantContract;
use App\Enums\AppointmentSource;
use App\Interfaces\Main\AssistantSkillTool;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\Customer;
use App\Services\Agenda\SlotFinder;
use App\Traits\SpeaksAgenda;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

/**
 * Takes the slot for THIS customer. The hour is checked again on the way in:
 * the assistant offered it seconds ago and two people can ask for the same one.
 */
class BookCustomerAppointment implements AssistantSkillTool
{
    use SpeaksAgenda;

    public function __construct(
        private readonly Business $business,
        private readonly Customer $customer,
        private readonly ?Conversation $conversation = null,
    ) {}

    public static function forAssistant(AsistenteAtendia $assistant): ?static
    {
        return $assistant->business !== null && $assistant->business->appointments_enabled && $assistant->customer !== null
            ? new static($assistant->business, $assistant->customer, $assistant->conversation)
            : null;
    }

    public function description(): Stringable|string
    {
        return 'Reserva un turno para este cliente. Usala SOLO cuando el cliente ya eligió un '
            .'horario de los que le ofreciste. Pasá "starts_at" con el formato AAAA-MM-DD HH:MM, '
            .'"service" con el nombre del servicio si corresponde, y "notes" con lo que pidió.';
    }

    public function handle(Request $request): Stringable|string
    {
        $timezone = $this->business->localTimezone();
        $startsAt = AssistantContract::strictDate(trim((string) ($request['starts_at'] ?? '')), 'Y-m-d H:i', $timezone);

        if ($startsAt === null) {
            return 'Horario inválido: pasá "starts_at" como AAAA-MM-DD HH:MM, con una fecha que exista.';
        }

        $service = $this->bookableService($this->business, (string) ($request['service'] ?? ''));

        try {
            $appointment = app(BookAppointment::class)->handle(
                $this->business,
                $this->customer,
                $startsAt,
                $service,
                AppointmentSource::Assistant,
                trim((string) ($request['notes'] ?? '')) ?: null,
                $this->conversation?->id,
            );
        } catch (RuntimeException) {
            $free = app(SlotFinder::class)->freeSlots($this->business, $startsAt, $service);

            return 'Ese horario ya no está libre. '
                .($free === [] ? 'Ese día no queda ninguno.' : 'Libres ese día: '.$this->slotList($free));
        }

        return 'Turno reservado: '.$this->appointmentLabel($appointment, $this->business)
            .'. Confirmale el día y la hora al cliente, y decile que si necesita cambiarlo o cancelarlo te lo diga por acá.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'starts_at' => $schema->string()->required()->description('Inicio del turno elegido, formato AAAA-MM-DD HH:MM.'),
            'service' => $schema->string()->description('Nombre del servicio que reserva, si corresponde.'),
            'notes' => $schema->string()->description('Lo que pidió el cliente, en una línea.'),
        ];
    }
}
