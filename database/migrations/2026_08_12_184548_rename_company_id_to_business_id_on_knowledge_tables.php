<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The RAG tables were born with `company_id` meaning the customer's business.
 * `Company` is now the invoice issuer and the tenant is `Business`, so it
 * pointed at the wrong concept. A new migration and not an edit of the
 * originals, which already ran — and a real RENAME, since a migration that
 * drops a column holding data is a trap waiting for another environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->dropForeign('knowledge_documents_company_id_foreign');
            $table->dropIndex('knowledge_documents_company_id_status_index');
            $table->renameColumn('company_id', 'business_id');
        });

        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->index(['business_id', 'status']);
        });

        Schema::table('knowledge_chunks', function (Blueprint $table): void {
            $table->dropForeign('knowledge_chunks_company_id_foreign');
            $table->dropIndex('knowledge_chunks_company_id_index');
            $table->renameColumn('company_id', 'business_id');
        });

        Schema::table('knowledge_chunks', function (Blueprint $table): void {
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_chunks', function (Blueprint $table): void {
            $table->dropForeign('knowledge_chunks_business_id_foreign');
            $table->dropIndex('knowledge_chunks_business_id_index');
            $table->renameColumn('business_id', 'company_id');
        });

        Schema::table('knowledge_chunks', function (Blueprint $table): void {
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index('company_id');
        });

        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->dropForeign('knowledge_documents_business_id_foreign');
            $table->dropIndex('knowledge_documents_business_id_status_index');
            $table->renameColumn('business_id', 'company_id');
        });

        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['company_id', 'status']);
        });
    }
};
