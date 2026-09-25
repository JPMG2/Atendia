<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the assistant knows how to DO with the business's real data.
     * Universal skills ride every request; a trade's own skills travel
     * deferred behind ToolSearch and load only when a question needs them.
     */
    public function up(): void
    {
        Schema::create('assistant_skills', function (Blueprint $table): void {
            $table->id();

            $table->string('key', 40)->unique()->comment('Clave estable que mapea a la herramienta en config/atendia.php');
            $table->string('name', 80)->comment('Cómo lo lee el admin: Horarios, Catálogo, Turnos');
            $table->string('description')->nullable();
            $table->string('audience', 20)->default('customer')->index()->comment('customer = asistente de WhatsApp; owner = Pregúntale a AtendIa del panel');
            $table->boolean('is_universal')->default(false)->comment('true = todos los negocios; false = solo las actividades que lo tienen');
            $table->boolean('is_active')->default(true)->comment('Apagar un skill sin borrarlo');
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_skills');
    }
};
