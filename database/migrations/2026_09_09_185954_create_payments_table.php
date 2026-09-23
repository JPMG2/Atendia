<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every payment a business makes to Atendia. Payment-rail agnostic on
     * purpose: today a receipt the admin verifies, tomorrow whatever gateway
     * the fiscal meeting picks. Dated BEFORE the RLS migration so the tenant
     * policy covers it. Rows are never deleted: they are money records.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            $table->string('plan', 20)->comment('Plan pagado (config/atendia.php)');
            $table->string('billing_cycle', 10)->default('monthly')->comment('monthly | yearly');
            $table->decimal('amount', 10, 2)->comment('Monto a pagar del período');
            $table->string('currency', 3)->default('USD');
            $table->string('method', 20)->default('transfer')->comment('transfer hoy; el medio definitivo llega tras la reunión fiscal');
            $table->string('reference', 60)->nullable()->comment('Número de operación que informó el cliente');
            $table->string('receipt_path')->nullable()->comment('Comprobante subido, en businesses/{id}/payments (disco privado)');
            $table->string('status', 10)->default('pending')->comment('pending (por verificar) | paid | rejected');
            $table->string('rejection_reason')->nullable()->comment('Por qué se rechazó: lo lee el cliente');
            $table->timestamp('period_starts_at')->nullable()->comment('Período que cubre este pago (se fija al acreditarlo)');
            $table->timestamp('period_ends_at')->nullable();
            $table->timestamp('paid_at')->nullable()->comment('Cuándo se acreditó');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete()->comment('Quién lo acreditó o rechazó');
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
