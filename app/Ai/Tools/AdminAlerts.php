<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AskAdmin;
use App\Enums\PlatformEventSeverity;
use App\Interfaces\Main\AdminSkillTool;
use App\Models\PlatformEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/** The board's open notices, worst first, optionally for one business or one severity. */
class AdminAlerts implements AdminSkillTool
{
    private const int LIMIT = 10;

    /** The key of the board it reads. */
    public static function forAdmin(AskAdmin $assistant): ?static
    {
        return $assistant->admin->can('alerts.view') ? new static : null;
    }

    public function description(): Stringable|string
    {
        return 'Los avisos abiertos del tablero de la plataforma, los más graves primero: qué pasó, de qué negocio, '
            .'cuándo y cuántas veces. Se puede acotar por gravedad (critical, important, info) o por el número de un negocio.';
    }

    public function handle(Request $request): Stringable|string
    {
        $severity = PlatformEventSeverity::tryFrom((string) $request->string('severity'));
        $businessId = $request->integer('business_id') ?: null;

        $events = PlatformEvent::board(self::LIMIT, $severity, businessId: $businessId);

        if ($events->isEmpty()) {
            return 'No hay avisos abiertos'.($severity === null && $businessId === null ? '' : ' con ese filtro').'. Pantalla: '.route('admin.alerts');
        }

        $counts = PlatformEvent::counts();

        return "Avisos abiertos: {$counts['critical']} críticos, {$counts['important']} importantes, {$counts['info']} informativos. "
            .'Los '.$events->count()." primeros:\n- "
            .$events->map(fn (PlatformEvent $event): string => implode(' · ', array_filter([
                __('platform_events.severity.'.$event->severity->value),
                $event->kind->label(),
                $event->aboutBusiness?->name,
                $event->detail,
                $event->last_seen_at?->format('d/m/Y H:i'),
                $event->occurrences > 1 ? "{$event->occurrences} veces" : null,
                // What the board itself says to do, and the screen that does it: the next step comes from here, never from the model.
                'qué hacer: '.__('platform_events.kinds.'.$event->kind->value.'.hint'),
                $this->door($event),
            ])))->implode("\n- ")
            ."\nPantalla: ".route('admin.alerts');
    }

    /** The screen where this notice is worked, when it has one. */
    private function door(PlatformEvent $event): ?string
    {
        $target = $event->kind->target($event->about_business_id);

        return $target === null ? null : 'resolver en: '.route($target['route'], $target['query']);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'severity' => $schema->string()->description('critical, important o info. Vacío para todas.'),
            'business_id' => $schema->integer()->description('Número de un negocio para ver solo sus avisos. Vacío para todos.'),
        ];
    }
}
