<?php

declare(strict_types=1);

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
        Schema::create('catalog_forms', function (Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('component');
            $table->string('icon')->nullable();
            $table->integer('order')->default(0);
            $table->string('permission_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_forms');
    }
};
