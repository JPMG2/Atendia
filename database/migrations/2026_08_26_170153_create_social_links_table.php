<?php

declare(strict_types=1);

use App\Models\SocialNetwork;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The networks an account is on: AtendIa's and every customer business's.
 *
 * POLYMORPHIC on purpose. A social link does not change shape with its owner —
 * always a network plus a link — so twin tables would be one schema written
 * twice, and a branch tomorrow would make three. This is where morphs pay off,
 * unlike the business-to-sector axis where they were dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_links', function (Blueprint $table): void {
            $table->id();

            // The network is shared by every account, so it is never hard-deleted.
            $table->foreignIdFor(SocialNetwork::class)->constrained()->restrictOnDelete();

            // The link's owner: a Company or a Business, or whatever comes next.
            $table->morphs('linkable');

            $table->string('url')->comment('Enlace o usuario de la cuenta en esa red');

            // The footer promises to show them in the order they were added: with no
            // column, the database engine would pick that order.
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            // One account per network per owner: adding the same one twice is a
            // mistake, not a use case.
            $table->unique(['linkable_type', 'linkable_id', 'social_network_id'], 'social_links_owner_network_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_links');
    }
};
