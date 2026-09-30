<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AskAtendia;
use App\Enums\AppointmentStatus;
use App\Interfaces\Main\OwnerSkillTool;
use App\Models\Appointment;
use App\Models\Business;
use App\Traits\ReadsDateRange;
use App\Traits\SpeaksAgenda;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The owner's own agenda in a period: the SAME bookings the day view paints,
 * each with who comes, for what and how it ended. Read-only — a booking moves
 * from the sheet on the screen, never from here.
 */
class OwnerAppointments implements OwnerSkillTool
{
    use ReadsDateRange;
    use SpeaksAgenda;

    /** A year of a busy agenda would not fit an answer: the rest is a count. */
    private const int LISTED = 30;

    public function __construct(private readonly Business $business) {}

    public static function forOwner(AskAtendia $assistant): ?static
    {
        // A bakery gives no slots: it has no agenda to read and never pays for
        // this tool's description either.
        return $assistant->business->appointments_enabled
            ? new static($assistant->business)
            : null;
    }

    public function description(): Stringable|string
    {
        return 'Los turnos reservados del negocio en un período: día y hora, quién viene, qué '
            .'servicio y cuánto dura. Los que ya se atendieron y los que no vinieron lo dicen; '
            .'los cancelados no aparecen, porque dejaron su hora libre. Usala para "¿qué turnos '
            .'tengo mañana?", "¿cuántos turnos hay esta semana?" o "¿quién no vino ayer?".';
    }

    public function handle(Request $request): Stringable|string
    {
        $range = $this->dateRange($request, $this->business);

        if (is_string($range)) {
            return $range;
        }

        [$from, $to] = $range;
        $screen = 'Pantalla: '.route('agenda');
        $appointments = Appointment::between($this->business, $from, $to);

        if ($appointments->isEmpty()) {
            return $this->periodLabel($from, $to).' no hay ningún turno reservado. '.$screen;
        }

        $lines = $appointments->take(self::LISTED)
            ->map(fn (Appointment $appointment): string => '- '.$this->line($appointment))
            ->all();

        if ($appointments->count() > self::LISTED) {
            $lines[] = '- y '.($appointments->count() - self::LISTED).' turnos más en el período.';
        }

        return implode("\n", [
            $this->periodLabel($from, $to).': '.$appointments->count().' turnos.',
            ...$lines,
            $screen,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->dateRangeSchema($schema);
    }

    /**
     * One booking said out loud. The status only shows when it is NOT the one
     * every booking is born with: "confirmado" on each line is a word the
     * owner already knows and pays for.
     */
    private function line(Appointment $appointment): string
    {
        $when = $this->slotLabel($appointment->starts_at->setTimezone($this->business->localTimezone()));
        $customer = $appointment->customer;
        $who = $customer === null ? 'cliente borrado' : $customer->displayName() ?? '+'.$customer->phone;
        $what = $appointment->service?->name ?? 'turno general';
        $ended = match ($appointment->status) {
            AppointmentStatus::Done => ' · atendido',
            AppointmentStatus::NoShow => ' · no vino',
            default => '',
        };

        return "{$when} · {$who} · {$what} ({$appointment->durationMinutes()} min){$ended}{$this->thread($appointment)}";
    }

    /**
     * The chat where the slot was taken, so "¿quién no vino ayer?" ends in the
     * thread to write to them. Built from the id alone — no extra query, and
     * nullOnDelete means an id that is there is a thread that still exists.
     */
    private function thread(Appointment $appointment): string
    {
        return $appointment->conversation_id === null
            ? ''
            : ' · charla '.route('conversations', ['hilo' => $appointment->conversation_id]);
    }
}
