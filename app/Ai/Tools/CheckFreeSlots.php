<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AsistenteAtendia;
use App\Classes\Main\AssistantContract;
use App\Interfaces\Main\AssistantSkillTool;
use App\Models\Business;
use App\Services\Agenda\SlotFinder;
use App\Traits\SpeaksAgenda;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The real free hours of the agenda: the business's shifts minus what is
 * already booked. Live data no document can hold.
 */
class CheckFreeSlots implements AssistantSkillTool
{
    use SpeaksAgenda;

    public function __construct(private readonly Business $business) {}

    public static function forAssistant(AsistenteAtendia $assistant): ?static
    {
        return $assistant->business !== null && $assistant->business->appointments_enabled
            ? new static($assistant->business)
            : null;
    }

    public function description(): Stringable|string
    {
        return 'Devuelve los turnos LIBRES de la agenda del negocio. Usala siempre que pregunten '
            .'por disponibilidad, cuándo hay turno o si pueden reservar. Pasá "date" (AAAA-MM-DD) '
            .'si preguntan por un día puntual, y "service" con el nombre del servicio si lo dijeron; '
            .'sin fecha te devuelve los próximos huecos.';
    }

    public function handle(Request $request): Stringable|string
    {
        $timezone = $this->business->localTimezone();
        $service = $this->bookableService($this->business, (string) ($request['service'] ?? ''));
        $asked = trim((string) ($request['date'] ?? ''));
        $finder = app(SlotFinder::class);
        $duration = $finder->slotMinutes($this->business, $service);
        $forService = $service === null ? '' : " para {$service->name}";

        if ($asked === '') {
            $slots = $finder->nextSlots($this->business, $service, CarbonImmutable::now($timezone), 5);

            return $slots === []
                ? 'No hay turnos libres en los próximos 14 días.'
                : "Próximos turnos libres{$forService} (duran {$duration} min): ".$this->slotList($slots);
        }

        $date = AssistantContract::strictDate($asked, 'Y-m-d', $timezone);

        if ($date === null) {
            return 'Fecha inválida: usá el formato AAAA-MM-DD, con un día que exista.';
        }

        $slots = $finder->freeSlots($this->business, $date, $service);
        $day = $this->dayLabel($date);

        if ($slots !== []) {
            return "Turnos libres{$forService} el {$day} (duran {$duration} min): ".$this->slotList($slots);
        }

        // Nothing that day: the next real hour beats a bare "no".
        $next = $finder->nextSlots($this->business, $service, $date->addDay(), 3);

        return "No hay turnos libres{$forService} el {$day}."
            .($next === [] ? '' : ' Los siguientes libres: '.$this->slotList($next));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()->description('Día por el que preguntan, formato AAAA-MM-DD. Vacío = los próximos huecos.'),
            'service' => $schema->string()->description('Nombre del servicio que quieren reservar, si lo dijeron.'),
        ];
    }
}
