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
     * tables documented. One row per business for now; billing history and
     * Mercado Pago columns arrive with the payment phase.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();

            // The tenant: the plan belongs to the business, not the user.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('plan', 20)->comment('Código del plan en config/atendia.php (emprende|negocio|premium)');
            $table->timestamp('trial_ends_at')->nullable()->comment('Fin del reverse trial; vencido sin pago cae al plan piso');
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
