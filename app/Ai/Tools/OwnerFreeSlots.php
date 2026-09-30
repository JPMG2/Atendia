<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AskAtendia;
use App\Classes\Main\AssistantContract;
use App\Interfaces\Main\OwnerSkillTool;
use App\Models\Business;
use App\Services\Agenda\SlotFinder;
use App\Traits\SpeaksAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The hours the agenda still has free, read for the owner. The SAME finder
 * the customer's assistant offers from, so what she is told is free is what
 * the next customer can take.
 */
class OwnerFreeSlots implements OwnerSkillTool
{
    use SpeaksAgenda;

    /** Enough to see the shape of the day without printing a whole shift. */
    private const int LISTED = 12;

    public function __construct(private readonly Business $business) {}

    public static function forOwner(AskAtendia $assistant): ?static
    {
        // A business that gives no slots has no free hours, and never pays for
        // this tool's description either.
        return $assistant->business->appointments_enabled
            ? new static($assistant->business)
            : null;
    }

    public function description(): Stringable|string
    {
        return 'Las horas que le quedan LIBRES a la agenda: los horarios del negocio menos lo ya '
            .'reservado. Pasá "date" (AAAA-MM-DD) para un día puntual; sin fecha devuelve los '
            .'próximos huecos. Con "service" el nombre de un servicio, si preguntan por uno. Usala '
            .'para "¿me queda algo hoy?", "¿cuántos huecos tengo el viernes?" o "¿estoy lleno?".';
    }

    public function handle(Request $request): Stringable|string
    {
        $timezone = $this->business->localTimezone();
        $service = $this->bookableService($this->business, (string) ($request['service'] ?? ''));
        $forService = $service === null ? '' : " para {$service->name}";
        $finder = app(SlotFinder::class);
        $duration = $finder->slotMinutes($this->business, $service);
        $screen = 'Pantalla: '.route('agenda');
        $asked = trim((string) ($request['date'] ?? ''));

        if ($asked === '') {
            $next = $finder->nextSlots($this->business, $service, CarbonImmutable::now($timezone), self::LISTED);

            return ($next === []
                ? "No queda ninguna hora libre{$forService} en los próximos 14 días."
                : "Próximas horas libres{$forService} (de {$duration} min): ".$this->slotList($next)).' '.$screen;
        }

        $date = AssistantContract::strictDate($asked, 'Y-m-d', $timezone);

        if ($date === null) {
            return 'Fecha inválida: usá el formato AAAA-MM-DD, con un día que exista.';
        }

        $slots = $finder->freeSlots($this->business, $date, $service);
        $day = $this->dayLabel($date);

        if ($slots === []) {
            // A bare "no" hides which of the two it is: closed, or full.
            return "No queda ninguna hora libre{$forService} el {$day}. "
                .'Puede ser que el día esté completo o que el negocio no abra. '.$screen;
        }

        return count($slots).' horas libres'.$forService." el {$day} (de {$duration} min): "
            .$this->slotList(array_slice($slots, 0, self::LISTED))
            .(count($slots) > self::LISTED ? ' y '.(count($slots) - self::LISTED).' más.' : '.')
            .' '.$screen;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()->description('Día por el que se pregunta, formato AAAA-MM-DD. Vacío = los próximos huecos.'),
            'service' => $schema->string()->description('Nombre del servicio, si se preguntó por uno.'),
        ];
    }
}
