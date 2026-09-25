<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The owner's thumbs on "Ask AtendIa" answers: the question and the
     * answer are kept as they were, since the thread itself is never stored.
     */
    public function up(): void
    {
        Schema::create('ask_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->text('question');
            $table->text('answer');
            $table->string('rating', 10)->comment('up | down');

            $table->timestamps();

            $table->index(['business_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ask_feedback');
    }
};
