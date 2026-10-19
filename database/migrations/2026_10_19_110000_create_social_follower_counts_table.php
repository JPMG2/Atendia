<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each of the platform's own accounts had on the day somebody looked, so
 * the mentions can be measured against the followers they bring. Written by
 * hand until the networks give it to the API; one count per account per day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_follower_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('social_link_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('followers');
            $table->date('counted_on');
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['social_link_id', 'counted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_follower_counts');
    }
};
