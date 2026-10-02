<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AtendIa's own answers. NOT tenant data: the same article serves every
 * business, so no business_id and no RLS — this is our documentation.
 * `screen` lets the panel pick the right ones instead of leaving it to a
 * search the person may not know how to phrase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('category', 40);
            $table->string('screen', 80)->nullable()->comment('Route name it belongs to; null means it is about the product as a whole');
            $table->string('title', 180)->comment('The task as the person would type it, never the topic');
            $table->text('body')->comment('The answer in the first lines, then the numbered steps');
            $table->string('keywords', 300)->nullable()->comment('What she would call it when it is not what we call it');
            // The meaning lane, for when she names the problem with words we
            // never wrote. Filled by atendia:embed-help, never on save.
            $table->vector('embedding', dimensions: config('rag.embedding.dimensions'))->nullable()->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('opened_count')->default(0)->comment('Against the tickets opened from help: that ratio is whether any of this works');
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('unhelpful_count')->default(0)->comment('The one that matters: people mostly vote when it did not help');
            $table->timestamps();

            $table->index(['screen', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_articles');
    }
};
