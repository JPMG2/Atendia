<?php

use App\Models\Country;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tax_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Country::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->boolean('discriminate_tax')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // La condición fiscal es única DENTRO de cada país, no a nivel global:
            // el mismo código/nombre puede existir en más de un país.
            $table->unique(['country_id', 'code']);
            $table->unique(['country_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_conditions');
    }
};
