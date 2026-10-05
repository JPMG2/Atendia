<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The behaviour knobs she may turn without a deploy.
     *
     * The `key` is the dotted path under `atendia.` in `config/atendia.php`,
     * because the row OVERRIDES that config at boot: the fifteen places that
     * already read the config keep working untouched, the file stays as the
     * written default, and what she edits wins. Platform data, not tenant.
     */
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();

            $table->string('key', 80)->unique()->comment('Ruta bajo atendia. en config/atendia.php, ej. analysis.idle_hours');
            $table->string('group', 40)->comment('Agrupa la pantalla por TAREA, no por archivo de config');
            $table->string('type', 20)->comment('integer | time | weekday | decimal');
            $table->string('value', 40)->comment('Lo que ella puso; pisa al config');
            $table->string('default_value', 40)->comment('Lo que trae el código: siempre hay cómo volver');
            // A threshold with no floor or ceiling is a way to break the
            // product from a text field: 0 hours would analyse a live chat.
            $table->decimal('min_value', 8, 2)->nullable();
            $table->decimal('max_value', 8, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
