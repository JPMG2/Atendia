<?php

declare(strict_types=1);

use App\Models\BusinessActivity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Muda la actividad del negocio a `activity_business` y saca la columna.
 *
 * La que estaba en `businesses.business_activity_id` pasa a ser la PRINCIPAL. La
 * columna se va: dejarla como "la principal" y el pivot para "las demás" serían
 * dos fuentes de verdad, y tarde o temprano se contradicen.
 *
 * Se hace ahora, antes de que haya negocios cargados: es la migración cara de
 * esta feature y el momento más barato para pagarla.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Backfill primero: si la columna se fuera antes, el dato se perdería.
        $rows = DB::table('businesses')
            ->whereNotNull('business_activity_id')
            ->get(['id', 'business_activity_id']);

        foreach ($rows as $row) {
            DB::table('activity_business')->insertOrIgnore([
                'business_id' => $row->id,
                'business_activity_id' => $row->business_activity_id,
                'is_primary' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_activity_id');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->foreignIdFor(BusinessActivity::class)
                ->nullable()
                ->after('country_id')
                ->constrained()
                ->restrictOnDelete();
        });

        // Vuelve solo la principal: la columna no puede guardar las secundarias.
        $primaries = DB::table('activity_business')->where('is_primary', true)->get();

        foreach ($primaries as $primary) {
            DB::table('businesses')
                ->where('id', $primary->business_id)
                ->update(['business_activity_id' => $primary->business_activity_id]);
        }
    }
};
