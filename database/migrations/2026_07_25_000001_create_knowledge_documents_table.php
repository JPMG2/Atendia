<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Knowledge documents: the unit a user manages — an FAQ, a policy, a guide.
     * It is the source, split and embedded into `knowledge_chunks`, and always
     * scoped by tenant.
     */
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('source_type')->default('manual'); // manual | faq | file | url
            $table->longText('content');
            $table->string('content_hash', 64)->nullable(); // re-index only when it changed
            $table->string('status')->default('pending');    // pending | indexed | failed
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
