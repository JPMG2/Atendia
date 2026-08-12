<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fragmentos embeddados de un documento: lo que se busca por similitud.
     * `company_id` va DENORMALIZADO para filtrar por tenant sin join. La columna
     * `vector(1536)` con `->index()` genera un índice HNSW + vector_cosine_ops
     * automáticamente (Laravel 13). La extensión `vector` ya se crea en su propia
     * migración (2026_07_10_enable_pgvector_extension).
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
