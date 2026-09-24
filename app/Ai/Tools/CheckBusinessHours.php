<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AsistenteAtendia;
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
            .'días de atención o si están abiertos.';
    }

    public function handle(Request $request): Stringable|string
    {
        $lines = $this->business->scheduleLines();

        if ($lines === []) {
            return 'El negocio no cargó sus horarios de atención.';
        }

        $now = now($this->business->localTimezone());
        $today = BusinessHour::dayNames()[(int) $now->format('w')];
        $state = $this->business->isOpenNow() ? 'abierto' : 'cerrado';

        return "Ahora es {$today}, {$now->format('H:i')}: el negocio está {$state}.\nHorarios de atención:\n".implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
