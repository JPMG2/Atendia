<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The message that opens the mail to an account stuck on a step of the adoption
 * ladder: one per step, written once in the admin. The step stays in code (the
 * screen branches on it); only the words are data she can rewrite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adoption_nudges', function (Blueprint $table): void {
            $table->id();
            $table->string('step', 32)->unique()->comment('registered, business, catalog, whatsapp, conversation');
            $table->string('subject');
            $table->text('body');
            $table->boolean('is_active')->default(true);

            // Deleting the user leaves the message: only the author is lost.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adoption_nudges');
    }
};
