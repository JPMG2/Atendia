<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AsistenteAtendia;
use App\Classes\Main\AssistantContract;
use App\Interfaces\Main\AssistantSkillTool;
use App\Models\Business;
use App\Models\BusinessHour;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The week's hours plus what no document can know: today, the time and
 * whether it is open right now, in the business's own timezone. A line
 * instead of the knowledge chunks that used to carry the schedule.
 */
class CheckBusinessHours implements AssistantSkillTool
{
    public function __construct(private readonly Business $business) {}

    public static function forAssistant(AsistenteAtendia $assistant): ?static
    {
        return $assistant->business !== null ? new static($assistant->business) : null;
    }

    public function description(): Stringable|string
    {
        return 'Devuelve los horarios de atención del negocio, qué día y hora es ahora '
            .'y si está abierto en este momento. Usala para cualquier consulta de horarios, '
            .'días de atención o si están abiertos. Si preguntan por una fecha puntual '
            .'("el 12/10", "el 20 de diciembre"), pasala en "date" y te dice qué día cae.';
    }

    public function handle(Request $request): Stringable|string
    {
        $lines = $this->business->scheduleLines();

        if ($lines === []) {
            return 'El negocio no cargó sus horarios de atención.';
        }

        $timezone = $this->business->localTimezone();
        $now = now($timezone);
        $days = BusinessHour::dayNames();
        $state = $this->business->isOpenNow() ? 'abierto' : 'cerrado';
        $answer = "Ahora es {$days[(int) $now->format('w')]}, {$now->format('H:i')}: el negocio está {$state}.";

        $asked = trim((string) ($request['date'] ?? ''));

        if ($asked !== '') {
            // The weekday of a far date is computed here: the model miscounted
            // past its ±7-day clock and answered "no pude confirmar" (ai-eval).
            $date = AssistantContract::strictDate($asked, 'Y-m-d', $timezone);

            if ($date === null) {
                return 'Fecha inválida: usá el formato AAAA-MM-DD, con un día que exista.';
            }

            $weekday = $days[(int) $date->format('w')];
            $answer .= "\nEl {$date->format('d/m/Y')} es {$weekday}. Horario de ese día: "
                .(collect($lines)->first(fn (string $line): bool => str_starts_with($line, $weekday.':')) ?? $weekday.': cerrado')
                .'. No hay feriados ni cierres especiales cargados: es el horario habitual de ese día.';
        }

        return $answer."\nHorarios de atención:\n".implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()->description('Opcional: fecha puntual por la que preguntan, formato AAAA-MM-DD.'),
        ];
    }
}
