<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a business can report from the support widget (problem, idea, question).
 *
 * It was an Enum, but nothing in the code branches on WHICH kind it is: the
 * label, the icon, the priority a report starts with and whether it runs a
 * clock are all data, and the owner can rename or add one. `support_tickets.kind`
 * keeps holding the `code`, so no ticket needs rewriting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ticket_kinds', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique()->comment('problem, idea, question — the key support_tickets.kind holds');
            $table->string('name', 80)->comment('What the business reads on the chip');
            $table->string('icon', 40)->default('message-circle')->comment('Name in config/icons.php');
            $table->string('starting_priority', 10)->default('normal')->comment('high | normal | low: where a report of this kind starts');
            $table->boolean('runs_clock')->default(true)->comment('False = never turns overdue (an idea waits without anyone being stuck)');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            // Deleting the user leaves the row: only the author is lost.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_kinds');
    }
};
