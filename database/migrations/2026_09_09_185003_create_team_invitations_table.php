<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_invitations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('name', 120)->nullable();
            $table->string('email');
            $table->string('whatsapp', 30)->nullable()->comment('Solo dígitos: el número donde le llegan los avisos');
            $table->jsonb('department_ids')->nullable();
            $table->string('token_hash', 64)->unique()->comment('SHA-256 del token del enlace: el token en claro solo viaja en el correo');
            $table->timestamp('expires_at');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['business_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};
