<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A document's embedded chunks: what similarity search runs against.
     *
     * The tenant column is DENORMALISED so filtering needs no join, and the
     * `vector` column with `->index()` builds an HNSW index automatically. The
     * extension itself is created in its own migration.
     */
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->longText('content');
            $table->unsignedInteger('token_count')->default(0);
            $table->vector('embedding', dimensions: config('rag.embedding.dimensions'))->index();
            $table->timestamps();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
