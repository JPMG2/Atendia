<?php

declare(strict_types=1);

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

            // A tax standing is unique WITHIN a country, not globally: the same code
            // or name can exist in more than one.
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
