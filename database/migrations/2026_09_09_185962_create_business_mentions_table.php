<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A business answering the offer to be mentioned on the platform's networks.
     * Dated BEFORE the RLS migration, like the other tenant tables, so the
     * rebuild applies the policy. The goal and the caption are snapshots:
     * editing the master later never rewrites what the business accepted.
     */
    public function up(): void
    {
        Schema::create('business_mentions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_network_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('goal_threshold')->comment('La meta alcanzada: conversaciones atendidas en un mes');
            $table->unsignedInteger('conversations')->comment('Lo que llevaba el negocio al ofrecerle la mención');
            $table->string('status', 16)->comment('dismissed | pending | not_following | ready | published | withdrawn');

            $table->string('business_account_url')->nullable()->comment('La cuenta del negocio, copiada al aceptar');
            $table->json('platform_accounts')->nullable()->comment('Las cuentas de la plataforma que debe seguir [{network, url}], copiadas al aceptar');
            $table->text('caption')->nullable()->comment('El texto del post ya armado al aceptar');

            $table->timestamp('answered_at')->comment('Cuándo aceptó o dijo "ahora no"');
            $table->timestamp('verified_at')->nullable()->comment('Cuándo se comprobó que sigue la cuenta');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->string('post_url')->nullable()->comment('El enlace de la mención publicada');

            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_mentions');
    }
};
