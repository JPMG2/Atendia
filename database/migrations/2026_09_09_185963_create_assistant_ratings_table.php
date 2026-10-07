<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the business thought of one reply of its assistant.
     *
     * The customer is never asked: on a non-official number an extra message
     * is what gets the line banned, and it would be the BUSINESS's number.
     * Dated after `conversation_messages`, which it points at, and before the
     * RLS migration, which has to find the table there.
     */
    public function up(): void
    {
        Schema::create('assistant_ratings', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Quién la marcó; queda la marca aunque la persona se vaya');

            $table->boolean('is_good')->comment('true = la respuesta sirvió; false = estuvo mal');
            $table->string('reason', 60)->nullable()->comment('Qué estuvo mal, de una lista corta');

            $table->timestamps();

            // One mark per person per reply: marcar dos veces corrige, no suma.
            $table->unique(['conversation_message_id', 'user_id']);

            // The desk reads the bad ones of the last days, newest first.
            $table->index(['business_id', 'is_good', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_ratings');
    }
};
