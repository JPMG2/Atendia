<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The answers support gives again and again, so they are written once, in the
 * admin, and pasted into the composer. The body is capped like a reply (900)
 * because it ends up in one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_replies', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique()->comment('Reconectar el WhatsApp, Ya lo arreglamos');
            $table->text('body');
            $table->boolean('is_active')->default(true);

            // Deleting the user leaves the reply: only the author is lost.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_replies');
    }
};
