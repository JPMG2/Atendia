<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What content moderation caught in a business's uploads or texts. Only a
     * fingerprint is kept, never the file: what to preserve as evidence is a
     * call for the lawyer. Dated BEFORE the RLS migration so the policy covers it.
     */
    public function up(): void
    {
        Schema::create('moderation_flags', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('source', 20)->comment('Dónde apareció: logo, avatar, receipt, profile, service, product, faq');
            $table->string('kind', 10)->comment('image | text');
            $table->string('severity', 10)->comment('rejected (se rechazó, revisa el admin) | severe (suspendió el negocio)');
            $table->string('category', 60)->comment('Categoría de moderación con mayor puntaje');
            $table->decimal('score', 5, 4)->comment('Puntaje de esa categoría, de 0 a 1');
            $table->string('fingerprint', 64)->comment('SHA-256 del archivo o texto: el contenido no se guarda');
            $table->timestamp('reviewed_at')->nullable()->comment('Cuándo lo revisó el admin');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_flags');
    }
};
