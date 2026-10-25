<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AskAdmin;
use App\Interfaces\Main\AdminSkillTool;
use App\Models\Business;
use App\Models\Payment;
use App\Models\PlatformEvent;
use App\Models\PlatformSensor;
use App\Models\Subscription;
use App\Services\PlatformWhatsApp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/** The admin Inicio and the board in one read: businesses by situation, money, what waits and what is red. */
class AdminPlatformPulse implements AdminSkillTool
{
    /** The same cuts the businesses screen offers, so a count here matches that list. */
    private const array SITUATIONS = ['trialing', 'active', 'past_due', 'paused', 'canceling', 'ended', 'suspended', 'none'];

    /** It sums businesses, money and notices, so it needs the keys of all three screens it summarises. */
    public static function forAdmin(AskAdmin $assistant): ?static
    {
        return $assistant->admin->can('businesses.view') && $assistant->admin->can('payments.view') && $assistant->admin->can('alerts.view')
            ? new static
            : null;
    }

    public function description(): Stringable|string
    {
        return 'El pulso de la plataforma ahora: negocios por situación, ingreso mensual recurrente y cobrado, '
            .'comprobantes esperando, avisos del tablero abiertos, sensores en problema y estado de la línea de WhatsApp de la plataforma.';
    }

    public function handle(Request $request): Stringable|string
    {
        $situations = collect(self::SITUATIONS)
            ->mapWithKeys(fn (string $situation): array => [$situation => Business::directoryMatching('', $situation)])
            ->filter()
            ->map(fn (int $count, string $situation): string => __('admin.businesses.states.'.$situation).': '.$count)
            ->implode(' · ');

        $events = PlatformEvent::counts();
        $sensors = PlatformSensor::summary();
        $line = PlatformWhatsApp::lastKnown();
        $oldest = Payment::oldestPendingAgeInDays();

        return implode("\n", array_filter([
            'Negocios: '.Business::directoryTotal().($situations === '' ? '' : " ({$situations}).").' Pantalla: '.route('admin.businesses'),
            'Pagando: '.Subscription::payingCount().' · en prueba: '.Subscription::trialingCount().'.',
            'Ingreso mensual recurrente: USD '.number_format(Subscription::monthlyRecurringRevenue(), 2, ',', '.')
                .' · cobrado en 30 días: USD '.number_format(Payment::collectedInTheLastDays(30), 2, ',', '.').'.',
            'Comprobantes por verificar: '.Payment::pendingReviewCount()
                .($oldest === null ? '' : " (el más viejo espera {$oldest} días)").'. Pantalla: '.route('admin.payments'),
            "Avisos del tablero abiertos: {$events['critical']} críticos, {$events['important']} importantes, {$events['info']} informativos. Pantalla: ".route('admin.alerts'),
            "Sensores: {$sensors['ok']} de {$sensors['total']} en verde, {$sensors['problems']} en problema.",
            'WhatsApp de la plataforma: '.__('platform_whatsapp.status.'.$line['state']->value)
                .($line['at'] === null ? '' : ' (leído '.$line['at']->diffForHumans().')').'. Pantalla: '.route('admin.platform-whatsapp'),
            'Datos leídos ahora, '.now()->format('d/m/Y H:i').'.',
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
