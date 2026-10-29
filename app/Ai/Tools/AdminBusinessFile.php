<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AskAdmin;
use App\Enums\WhatsAppLinkState;
use App\Interfaces\Main\AdminSkillTool;
use App\Models\Business;
use App\Models\Payment;
use App\Models\PlatformEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * One business's file, read back from the database by the number the model
 * names: plan, money, usage, WhatsApp and open notices. Counts and dates only:
 * what the business's own customers wrote stays out until the lawyer says so (R-10).
 */
class AdminBusinessFile implements AdminSkillTool
{
    private const int PAYMENTS = 5;

    /** The ledger of a business is the businesses screen's own: same key. */
    public static function forAdmin(AskAdmin $assistant): ?static
    {
        return $assistant->admin->can('businesses.view') ? new static : null;
    }

    public function description(): Stringable|string
    {
        return 'La ficha de UN negocio por su número: situación, plan, prueba, próximo cobro, últimos pagos, uso del mes '
            .'contra su plan, estado de su WhatsApp, equipo y avisos abiertos. Nunca incluye lo que escriben sus clientes.';
    }

    public function handle(Request $request): Stringable|string
    {
        $business = Business::ledger((int) $request->integer('business_id'), self::PAYMENTS);

        if ($business === null) {
            return 'No existe un negocio con ese número. Búscalo por nombre con la herramienta de búsqueda.';
        }

        $plan = $business->plan();
        $subscription = $business->subscription;
        $trialDays = $subscription?->trialDaysLeft();

        return implode("\n", array_filter([
            "Negocio #{$business->id}: {$business->name}.",
            'Situación: '.__('admin.businesses.states.'.$business->situation()).'.',
            $subscription === null
                ? 'Sin plan contratado.'
                : 'Plan: '.__('plan.names.'.$plan->code)
                    .($trialDays !== null ? ", prueba: quedan {$trialDays} días" : '')
                    .($subscription->current_period_ends_at === null ? '' : ', período hasta '.$subscription->current_period_ends_at->format('d/m/Y'))
                    .', próximo cobro USD '.number_format($subscription->nextAmount(), 2, ',', '.').'.',
            'Contacto: '.collect([$business->email, $business->whatsapp_number])->filter()->implode(' · ').'.',
            'WhatsApp del negocio: '.__('whatsapp.topbar_'.match ($business->linkState()) {
                WhatsAppLinkState::Connected => 'connected',
                WhatsAppLinkState::Unverified => 'unverified',
                WhatsAppLinkState::Disconnected => 'disconnected',
            }).'.',
            'Uso de este mes: '.$business->conversationsThisMonth()." de {$plan->conversationsPerMonth} conversaciones con IA · "
                .(int) ceil($business->audioSecondsThisMonth() / 60).' minutos de audio · '
                .$business->askQuestionsThisMonth().' consultas a su asistente · '
                .$business->teamSeatsUsed()." de {$plan->teamSeats} personas en el panel.",
            $this->payments($business),
            $this->notices($business),
            'Ficha en pantalla: '.route('admin.businesses', ['negocio' => $business->id]),
        ]));
    }

    private function payments(Business $business): string
    {
        if ($business->payments->isEmpty()) {
            return 'Pagos: ninguno registrado.';
        }

        return "Últimos pagos:\n- ".$business->payments->map(fn (Payment $payment): string => implode(' · ', array_filter([
            $payment->created_at?->format('d/m/Y'),
            $payment->currency.' '.number_format((float) $payment->amount, 2, ',', '.'),
            __('billing.statuses.'.$payment->status->value),
            $payment->method,
        ])))->implode("\n- ");
    }

    private function notices(Business $business): string
    {
        $events = PlatformEvent::board(5, businessId: (int) $business->id);

        if ($events->isEmpty()) {
            return 'Avisos abiertos del tablero: ninguno.';
        }

        return "Avisos abiertos del tablero:\n- ".$events->map(fn (PlatformEvent $event): string => $event->kind->label()
            .' ('.__('platform_events.severity.'.$event->severity->value).', '.$event->last_seen_at?->format('d/m/Y H:i').')')->implode("\n- ");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'business_id' => $schema->integer()->description('El número del negocio, el que devuelve la búsqueda.')->required(),
        ];
    }
}
