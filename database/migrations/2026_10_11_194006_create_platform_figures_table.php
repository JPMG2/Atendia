<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Platform-level on purpose: no business_id. Each row is a figure ABOUT
        // every business at once, computed by a job and read as it was left.
        Schema::create('platform_figures', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->jsonb('payload');
            $table->timestamp('computed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_figures');
    }
};
