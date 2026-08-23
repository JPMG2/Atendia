<?php

declare(strict_types=1);

use App\Models\BusinessActivity;
use App\Models\ServiceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué tipos de servicio se le SUGIEREN a cada actividad.
 *
 * La palabra es sugerencia y no permiso, y es la decisión que sostiene todo el
 * producto: el catálogo ofrece, nunca prohíbe. La peluquería no ve "Pedido para
 * llevar" porque nadie se lo sugirió; la panadería que quiere poner mesas
 * igual puede adoptar "Mesa". Es el modelo de Google Business Profile, donde los
 * atributos que te ofrecen dependen de tu categoría pero podés declarar hasta
 * nueve categorías más.
 *
 * Por eso NO hay una constraint que impida adoptar un tipo no sugerido: la
 * ausencia de una fila acá significa "no se lo muestro arriba", no "no puede".
 *
 * Cuelga de la ACTIVIDAD y no del rubro: Panadería y Restaurante son ambos
 * Gastronomía y no ofrecen lo mismo. Para no cargar cuarenta filas a mano, la
 * pantalla del admin va a ofrecer "aplicar a todo el rubro", que expande las
 * filas — la expansión es UI, la tabla sigue siendo una sola.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_service_type', function (Blueprint $table): void {
            $table->id();

            // Si la actividad desaparece, sus sugerencias no significan nada.
            $table->foreignIdFor(BusinessActivity::class)->constrained()->cascadeOnDelete();

            // El tipo en cambio se comparte entre actividades: no se borra en duro.
            $table->foreignIdFor(ServiceType::class)->constrained()->restrictOnDelete();

            $table->unsignedSmallInteger('sort_order')->default(0)
                ->comment('En qué orden se le sugieren al negocio de esta actividad');

            $table->timestamps();

            $table->unique(['business_activity_id', 'service_type_id'], 'activity_service_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_service_type');
    }
};
