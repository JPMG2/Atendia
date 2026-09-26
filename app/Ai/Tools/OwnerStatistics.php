<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AskAtendia;
use App\Classes\Main\AssistantContract;
use App\Classes\Main\Statistics;
use App\Interfaces\Main\OwnerSkillTool;
use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The "Mis estadísticas" screen read aloud: the SAME Statistics piece, so
 * the assistant can never disagree with the chart the owner is looking at,
 * and at the plan's depth — nothing above what the plan pays for.
 */
class OwnerStatistics implements OwnerSkillTool
{
    public function __construct(private readonly Business $business) {}

    public static function forOwner(AskAtendia $assistant): ?static
    {
        return new static($assistant->business);
    }

    public function description(): Stringable|string
    {
        return 'Los números de la pantalla "Mis estadísticas" para un mes: conversaciones, contactos '
            .'nuevos, preguntas, porcentaje que resolvió el asistente, minutos de audio y clientes '
            .'recuperados, comparados con el mes anterior. Del mes en curso suma, según el plan, los '
            .'temas más preguntados, el mejor día y, en Premium, las horas pico, la tendencia y lo que piden y no está en el catálogo.';
    }

    public function handle(Request $request): Stringable|string
    {
        $timezone = $this->business->localTimezone();
        $current = CarbonImmutable::now($timezone)->startOfMonth();

        $month = AssistantContract::strictDate((string) $request['month'], 'Y-m', $timezone);

        if ($month === null) {
            return 'Mes inválido: usá el formato AAAA-MM, con un mes que exista.';
        }

        $stats = new Statistics($this->business);
        $plan = $this->business->plan();
        $kpis = $stats->monthKpis($month);
        $previous = $stats->monthKpis($month->subMonthNoOverflow());

        $lines = ['Estadísticas de '.$month->locale('es')->translatedFormat('F Y').' (entre paréntesis, el mes anterior):'];

        foreach ([
            'conversations' => 'Conversaciones',
            'new_contacts' => 'Contactos nuevos',
            'questions' => 'Preguntas de clientes',
            'resolution' => 'Resueltas por el asistente (%)',
            'audio_minutes' => 'Minutos de audio',
            'recovered' => 'Clientes recuperados',
        ] as $key => $label) {
            $lines[] = "- {$label}: {$kpis[$key]} ({$previous[$key]})";
        }

        if ($month->isSameMonth($current) && $plan->statisticsAtLeast('patterns')) {
            $lines = [...$lines, ...$this->patterns($stats)];
        }

        if ($month->isSameMonth($current) && $plan->statisticsAtLeast('trends')) {
            $lines = [...$lines, ...$this->trends($stats)];
        }

        $lines[] = 'Pantalla: '.route('statistics');

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'month' => $schema->string()->description('Mes a consultar, formato AAAA-MM.')->required(),
        ];
    }

    /** @return list<string> */
    private function patterns(Statistics $stats): array
    {
        $lines = [];
        $topics = array_slice($stats->topics, 0, 8);

        if ($topics !== []) {
            $lines[] = 'Temas más preguntados este mes (veces · % que resolvió solo el asistente):';

            foreach ($topics as $topic) {
                $lines[] = "- {$topic['topic']}: {$topic['asked']} · {$topic['alone']}%";
            }
        }

        $best = $stats->bestDay();

        if ($best !== null) {
            $lines[] = "Día con más conversaciones (últimos 30 días): {$best['label']}, {$best['count']}.";
        }

        return $lines;
    }

    /** @return list<string> */
    private function trends(Statistics $stats): array
    {
        $lines = [];
        $window = $stats->peakHours['window'];

        // Premium on the screen too: the gate mirrors it block by block.
        if ($window !== null) {
            $lines[] = "Horas pico este mes: de {$window['from']} a {$window['to']} h, con el {$window['share']}% de los mensajes.";
        }

        $lines[] = 'Conversaciones por mes: '.collect($stats->monthlyTrend())
            ->map(fn (array $month): string => "{$month['label']} {$month['count']}")
            ->implode(', ').'.';

        $gaps = $stats->catalogGaps();

        if ($gaps !== []) {
            $lines[] = 'Piden y no está en el catálogo: '.collect($gaps)
                ->map(fn (array $gap): string => "{$gap['sample']} ({$gap['count']})")
                ->implode(', ').'.';
        }

        return $lines;
    }
}
