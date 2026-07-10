<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Guards that the pgvector extension is enabled by migration on the
 * (RefreshDatabase) testing database, so vector columns can be used.
 */
test('the pgvector extension is enabled and usable', function () {
    $extension = DB::selectOne("SELECT extversion FROM pg_extension WHERE extname = 'vector'");

    expect($extension)->not->toBeNull();

    // The vector type works end to end: cosine distance of a vector with itself is 0.
    $distance = DB::selectOne("SELECT ('[1,0,0]'::vector <=> '[1,0,0]'::vector) AS d")->d;

    expect((float) $distance)->toBe(0.0);
});
