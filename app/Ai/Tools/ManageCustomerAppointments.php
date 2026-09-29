<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Agenda\CancelAppointment;
use App\Actions\Agenda\RescheduleAppointment;
use App\Ai\Agents\AsistenteAtendia;
use App\Classes\Main\AssistantContract;
use App\Interfaces\Main\AssistantSkillTool;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Services\Agenda\SlotFinder;
use App\Traits\SpeaksAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

/**
 * This customer's own bookings: list them, cancel one or move it. Only the
 * bookings of the customer on the other end are ever reachable, whatever id
 * the model passes.
 */
class ManageCustomerAppointments implements AssistantSkillTool
{
    use SpeaksAgenda;

    public function __construct(private readonly Business $business, private readonly Customer $customer) {}

    public static function forAssistant(AsistenteAtendia $assistant): ?static
    {
        return $assistant->business !== null && $assistant->business->appointments_enabled && $assistant->customer !== null
            ? new static($assistant->business, $assistant->customer)
            : null;
    }

    public function description(): Stringable|string
    {
        return 'Los turnos ya reservados de ESTE cliente: usá action="list" para ver los que tiene, '
            .'"cancel" para cancelar uno y "move" para cambiarlo de horario. Para cancelar o mover pasá '
            .'"appointment_id" (el número que te devuelve la lista) y, para mover, "new_starts_at" '
            .'(AAAA-MM-DD HH:MM) de un horario que ya hayas confirmado libre.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = (string) ($request['action'] ?? 'list');
        $upcoming = Appointment::upcomingFor($this->customer, CarbonImmutable::now($this->business->localTimezone()));

        if ($upcoming->isEmpty()) {
            return 'Este cliente no tiene turnos reservados.';
        }

        if ($action === 'list') {
            return 'Turnos de este cliente: '.$upcoming
                ->map(fn (Appointment $appointment): string => $this->appointmentLabel($appointment, $this->business))
                ->implode(' · ');
        }

        $appointment = $this->pick($request, $upcoming);

        if ($appointment === null) {
            return 'Ese número de turno no es de este cliente. Pedí la lista con action="list".';
        }

        return $action === 'cancel'
            ? $this->cancel($appointment)
            : $this->move($request, $appointment);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['list', 'cancel', 'move'])->required(),
            'appointment_id' => $schema->integer()->description('El número del turno, como lo devuelve la lista.'),
            'new_starts_at' => $schema->string()->description('Para mover: el nuevo inicio, formato AAAA-MM-DD HH:MM.'),
        ];
    }

    /** @param  Collection<int, Appointment>  $upcoming */
    private function pick(Request $request, Collection $upcoming): ?Appointment
    {
        $id = (int) ($request['appointment_id'] ?? 0);

        // One booking and no id: there is nothing to confuse.
        if ($id === 0 && $upcoming->count() === 1) {
            return $upcoming->first();
        }

        return $upcoming->firstWhere('id', $id);
    }

    private function cancel(Appointment $appointment): string
    {
        $label = $this->appointmentLabel($appointment, $this->business);
        app(CancelAppointment::class)->handle($appointment);

        return "Turno cancelado: {$label}. Confirmale al cliente que quedó cancelado.";
    }

    private function move(Request $request, Appointment $appointment): string
    {
        $timezone = $this->business->localTimezone();
        $startsAt = AssistantContract::strictDate(trim((string) ($request['new_starts_at'] ?? '')), 'Y-m-d H:i', $timezone);

        if ($startsAt === null) {
            return 'Horario inválido: pasá "new_starts_at" como AAAA-MM-DD HH:MM, con una fecha que exista.';
        }

        try {
            app(RescheduleAppointment::class)->handle($appointment, $startsAt);
        } catch (RuntimeException) {
            $free = app(SlotFinder::class)->freeSlots($this->business, $startsAt, $appointment->service);

            return 'Ese horario no está libre. '
                .($free === [] ? 'Ese día no queda ninguno.' : 'Libres ese día: '.$this->slotList($free));
        }

        return 'Turno movido: '.$this->appointmentLabel($appointment->fresh(), $this->business)
            .'. Confirmale el nuevo día y hora al cliente.';
    }
}
