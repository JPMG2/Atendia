<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Agents\AskAdmin;
use App\Interfaces\Main\AdminSkillTool;
use App\Models\Payment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/** The receipts waiting for her verdict, oldest first. Reading only: crediting or rejecting is a click in Pagos. */
class AdminPaymentQueue implements AdminSkillTool
{
    private const int LIMIT = 8;

    /** The key of the screen where the receipts are verified. */
    public static function forAdmin(AskAdmin $assistant): ?static
    {
        return $assistant->admin->can('payments.view') ? new static : null;
    }

    public function description(): Stringable|string
    {
        return 'Los comprobantes de pago que esperan verificación, el más viejo primero: negocio, plan, monto, método, '
            .'cuánto espera y el enlace a Pagos. Acreditar o rechazar se hace en esa pantalla, no acá.';
    }

    public function handle(Request $request): Stringable|string
    {
        $total = Payment::pendingReviewCount();

        if ($total === 0) {
            return 'No hay comprobantes esperando verificación. Pantalla: '.route('admin.payments');
        }

        $lines = Payment::reviewQueue(self::LIMIT)->map(fn (Payment $payment): string => implode(' · ', array_filter([
            $payment->business?->name,
            __('plan.names.'.$payment->plan),
            $payment->currency.' '.number_format((float) $payment->amount, 2, ',', '.'),
            $payment->method,
            'espera desde el '.$payment->created_at?->format('d/m/Y'),
        ])));

        return "Comprobantes por verificar: {$total}".($total > self::LIMIT ? ' (se muestran '.self::LIMIT.')' : '').":\n- "
            .$lines->implode("\n- ")."\nPantalla: ".route('admin.payments');
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
