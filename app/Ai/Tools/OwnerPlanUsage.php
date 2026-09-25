<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AskAtendia;
use App\Interfaces\Main\OwnerSkillTool;
use App\Models\Business;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/** The "Mi plan" meters in one read: what the plan includes and what is left this month. */
class OwnerPlanUsage implements OwnerSkillTool
{
    public function __construct(private readonly Business $business) {}

    public static function forOwner(AskAtendia $assistant): ?static
    {
        return new static($assistant->business);
    }

    public function description(): Stringable|string
    {
        return 'El plan del negocio y su uso de este mes: conversaciones con IA, minutos de audio, '
            .'consultas a este asistente, números de WhatsApp y días de prueba que quedan.';
    }

    public function handle(Request $request): Stringable|string
    {
        $plan = $this->business->plan();
        $trialDays = $this->business->subscription?->trialDaysLeft();

        return implode("\n", array_filter([
            'Plan: '.__('plan.names.'.$plan->code).'.',
            $trialDays !== null ? "Prueba gratis: quedan {$trialDays} días." : null,
            'Conversaciones con IA este mes: '.$this->business->conversationsThisMonth()." de {$plan->conversationsPerMonth}.",
            $plan->allowsAudio
                ? 'Minutos de audio este mes: '.(int) ceil($this->business->audioSecondsThisMonth() / 60)." de {$plan->audioMinutesPerMonth}."
                : 'El plan no incluye notas de voz.',
            'Consultas a este asistente este mes: '.$this->business->askQuestionsThisMonth()." de {$plan->askPerMonth}.",
            "Números de WhatsApp incluidos: {$plan->whatsappNumbers}.",
            'Pantalla: '.route('my-plan'),
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
