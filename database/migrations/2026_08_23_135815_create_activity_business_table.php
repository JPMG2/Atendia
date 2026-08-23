<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\BusinessActivity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las actividades que declara un negocio: UNA principal + las que sume.
 *
 * Es el mecanismo que destraba el caso que ordenó todo el diseño: la panadería
 * que pone mesas agrega "Cafetería" como actividad secundaria y ahí sí se le
 * empiezan a sugerir Mesa y los tipos del salón. Es exactamente lo que hace
 * Google Business Profile con su categoría principal + hasta nueve secundarias:
 * lo que te ofrecen depende de tus categorías, y las categorías las elegís vos.
 *
 * Antes esto era `businesses.business_activity_id`, una sola. La columna se va en
 * la migración siguiente: el negocio NO puede tener su actividad en dos lugares
 * distintos, por el mismo motivo por el que el rubro no se guarda aparte de la
 * actividad — dos fuentes de verdad terminan contradiciéndose.
 *
 * `is_primary` no es una columna decorativa: la principal es la que manda para el
 * tono del asistente, el paquete de conocimiento del oficio y los reportes. Por
 * eso hay un índice único PARCIAL que garantiza una sola principal por negocio —
 * un `unique(business_id, is_primary)` no serviría: prohibiría tener dos
 * secundarias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_business', function (Blueprint $table): void {
            $table->id();

            // Si el negocio se borra, sus declaraciones no significan nada.
            $table->foreignIdFor(Business::class)->constrained()->cascadeOnDelete();

            // La actividad se comparte entre negocios: no se borra en duro.
            $table->foreignIdFor(BusinessActivity::class)->constrained()->restrictOnDelete();

            $table->boolean('is_primary')->default(false)
                ->comment('La que manda para el tono del asistente y el conocimiento del oficio');
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['business_id', 'business_activity_id'], 'activity_business_unique');
        });

        // Una sola principal por negocio. Índice PARCIAL de Postgres: solo indexa
        // las filas con is_primary, así las secundarias pueden ser muchas.
        DB::statement('CREATE UNIQUE INDEX activity_business_one_primary ON activity_business (business_id) WHERE is_primary');
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_business');
    }
};
