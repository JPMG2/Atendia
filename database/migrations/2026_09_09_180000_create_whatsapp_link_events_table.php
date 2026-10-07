<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every time a number started or stopped answering. The business column
     * holds only the CURRENT state, so without this there is no way to say
     * how reliable the line has been — and "connected" is a weaker promise
     * than "it did not drop all week". Dated just before the RLS migration,
     * which has to find the table already there to protect it.
     */
    public function up(): void
    {
        Schema::create('whatsapp_link_events', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->boolean('connected')->comment('true = el número empezó a responder; false = dejó de hacerlo');
            $table->string('reason', 60)->nullable()->comment('Lo que lo causó cuando se sabe: device_removed, bridge_closed, paired');

            $table->timestamp('occurred_at')->comment('Cuándo pasó, que no es cuándo nos enteramos');

            $table->timestamps();

            // The history reads one business's last days, newest first.
            $table->index(['business_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_link_events');
    }
};
