<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las tablas del RAG nacieron con `company_id` significando "el negocio del
 * cliente". Ahora `Company` es el emisor de la factura (AtendIa) y el tenant es
 * `Business`, así que esa columna apuntaba al concepto equivocado.
 *
 * Se renombra con una migración nueva en vez de editar las originales: editarlas
 * no tendría efecto sobre `atendia`, donde ya corrieron.
 *
 * RENAME de verdad, no drop + add. Hoy ambas tablas están en cero registros, así
 * que daría lo mismo — pero una migración que borra una columna con datos es una
 * trampa esperando a otro entorno. Renombrar conserva lo que haya.
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
