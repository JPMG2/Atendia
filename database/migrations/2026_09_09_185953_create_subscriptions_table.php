<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The business's plan. Dated BEFORE the RLS migration on purpose so the
     * rebuild applies the tenant policy to it — same trap the conversations
     * tables documented. One row per business for now; each payment lives in
     * `payments`, and this row only tracks the current period and status.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();

            // The tenant: the plan belongs to the business, not the user.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('plan', 20)->comment('Código del plan en config/atendia.php (emprende|negocio|premium)');
            $table->timestamp('trial_ends_at')->nullable()->comment('Fin del reverse trial; vencido sin pago cae al plan piso');
            $table->string('billing_cycle', 10)->default('monthly')->comment('monthly | yearly: cuánto dura cada período pagado');
            $table->string('status', 12)->default('trialing')->comment('trialing | active | past_due (en gracia) | paused (asistente en pausa por falta de pago)');
            $table->timestamp('current_period_ends_at')->nullable()->comment('Fin del período en curso: la fecha del próximo pago. En prueba, el fin de la prueba');
            $table->timestamp('paused_at')->nullable()->comment('Cuándo se pausó el asistente por falta de pago; null = atiende');
            $table->timestamps();

            // No unique on purpose: plan changes append rows (billing history);
            // the CURRENT subscription is simply the latest one.
            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
