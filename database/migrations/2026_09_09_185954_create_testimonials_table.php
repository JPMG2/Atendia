<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One owner's word about AtendIa, asked at a success milestone. The row
     * carries the whole lifecycle: dismissed, pending, approved or rejected
     * by the admin — and only approved WITH consent ever reaches the landing.
     */
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->text('quote')->nullable()->comment('La frase del dueño; null cuando solo descartó el pedido');
            $table->unsignedTinyInteger('rating')->nullable()->comment('Estrellas 1-5 elegidas por el dueño; opcional, jamás inventadas');
            $table->string('display_name')->comment('Cómo se muestra en la landing: el nombre del negocio al enviar');
            $table->string('display_role')->nullable()->comment('El rubro mostrado bajo el nombre');
            $table->timestamp('consent_given_at')->nullable()->comment('Cuándo autorizó publicar con su nombre; null = solo feedback interno');
            $table->string('status', 12)->default('pending')->comment('pending | approved | rejected | dismissed');

            $table->timestamps();

            // One ask per business: the milestone card never nags twice.
            $table->unique('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
