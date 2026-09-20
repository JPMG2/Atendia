<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every question the knowledge base could NOT answer, recorded at the
     * exact moment the search came back empty. The owner's teaching queue:
     * once taught, the same question stops missing by itself.
     */
    public function up(): void
    {
        Schema::create('knowledge_misses', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('query', 500)->comment('Lo que el cliente preguntó y el conocimiento no cubrió');

            $table->timestamps();

            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_misses');
    }
};
