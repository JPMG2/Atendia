<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The attribute's cardinality: one value or many?
 *
 * A practice accepts several health insurers, and a service covers several
 * areas. Without this the business crams them into one string and the
 * assistant cannot filter. It is a boolean column TODAY and a migration of
 * every stored value TOMORROW, so it lands before the first value exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_attributes', function (Blueprint $table): void {
            $table->boolean('is_multiple')->default(false)->after('options')
                ->comment('Si admite varios valores a la vez (obras sociales, zonas)');
        });
    }

    public function down(): void
    {
        Schema::table('service_attributes', function (Blueprint $table): void {
            $table->dropColumn('is_multiple');
        });
    }
};
