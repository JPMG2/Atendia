<?php

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
        Schema::create('social_networks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Facebook, Instagram, Twitter');
            $table->string('url')->comment('https://www.facebook.com, https://www.instagram.com, https://www.twitter.com');
            $table->string('icon')->nullable()->comment('fab fa-facebook, fab fa-instagram, fab fa-twitter');
            $table->string('abbreviation', 10)->nullable()->comment('FB, IG, TW');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_networks');
    }
};
