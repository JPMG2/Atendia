<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photos of a product or a service, already shrunk: the original never
     * stays (a phone photo is 3-5 MB, ours ~170 KB). Dated BEFORE the RLS
     * migration so the tenant policy covers it.
     */
    public function up(): void
    {
        Schema::create('catalog_photos', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->morphs('photoable');

            $table->string('disk', 20)->comment('Disco donde vive: public hoy, spaces cuando estén las claves');
            $table->string('path')->comment('JPEG 1080 px para WhatsApp, en businesses/{id}/catalog');
            $table->string('thumb_path')->comment('Miniatura WebP 320 px para el panel');
            $table->unsignedInteger('bytes')->comment('Peso de ambas versiones juntas');
            $table->unsignedSmallInteger('sort_order')->default(0)->comment('La primera es la portada');
            $table->timestamps();

            $table->index(['business_id', 'photoable_type', 'photoable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_photos');
    }
};
