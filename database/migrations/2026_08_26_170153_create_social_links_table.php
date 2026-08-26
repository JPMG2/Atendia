<?php

declare(strict_types=1);

use App\Models\SocialNetwork;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las redes donde está una cuenta: la de AtendIa (`companies`) y la de cada
 * negocio cliente (`businesses`).
 *
 * Es POLIMÓRFICA a propósito. Un link social no cambia de forma según de quién
 * sea —siempre es "qué red" + "el enlace"—, así que dos tablas gemelas
 * (`company_social_network` y `business_social_network`) serían el mismo esquema
 * escrito dos veces, y sumar mañana una sucursal sería una tercera. Es el caso en
 * el que los morphs sí pagan (mismo criterio que direcciones y teléfonos), a
 * diferencia del eje negocio↔rubro, donde se descartaron.
 *
 * `social_networks` sigue siendo el CATÁLOGO (qué redes existen, con su ícono y
 * su url base); acá vive la CUENTA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_links', function (Blueprint $table): void {
            $table->id();

            // La red se comparte entre todas las cuentas: no se borra en duro.
            $table->foreignIdFor(SocialNetwork::class)->constrained()->restrictOnDelete();

            // El dueño del link: Company | Business (mañana, lo que sea).
            $table->morphs('linkable');

            $table->string('url')->comment('Enlace o usuario de la cuenta en esa red');

            // El pie de página promete mostrarlas en el orden que se cargaron:
            // sin columna, ese orden lo elegiría el motor de la base.
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            // Una sola cuenta por red y por dueño: cargar Instagram dos veces es
            // un error de carga, no un caso de uso.
            $table->unique(['linkable_type', 'linkable_id', 'social_network_id'], 'social_links_owner_network_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_links');
    }
};
