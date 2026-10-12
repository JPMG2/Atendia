<?php

declare(strict_types=1);

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The guard of the test database
|--------------------------------------------------------------------------
| A test that commits what it writes leaves rows every later test can see, and
| the symptom is a test that passes alone and fails in the full suite. The
| guard fails ONCE per run, naming the tables and the last test that finished
| before it. These pin that it sees a row, and that it stays quiet on a clean base.
*/

/** Puts the guard's per-process memory back, so each probe starts its own run. */
function resetLeakGuard(): void
{
    foreach (['leakReported' => false, 'checkedClasses' => [], 'baseline' => null] as $property => $value) {
        (new ReflectionProperty(TestCase::class, $property))->setValue(null, $value);
    }
}

test('a database that did not grow passes, with the catalog it was rebuilt with', function (): void {
    resetLeakGuard();

    // The first look is the baseline, which already holds the plans and their log.
    $this->assertNoCommittedRowsLeft();
    $this->assertNoCommittedRowsLeft();

    expect(true)->toBeTrue();
});

test('a row nobody rolled back is named, with the test that ran before', function (): void {
    resetLeakGuard();
    $this->assertNoCommittedRowsLeft();

    Business::factory()->create();

    $thrown = null;

    try {
        $this->assertNoCommittedRowsLeft();
    } catch (RuntimeException $exception) {
        $thrown = $exception->getMessage();
    }

    expect($thrown)->not->toBeNull()
        ->and($thrown)->toContain('businesses')
        ->and($thrown)->toContain('did not roll back');
});

test('it reports once: the second class that sees the same rows does not repeat it', function (): void {
    resetLeakGuard();
    $this->assertNoCommittedRowsLeft();
    Business::factory()->create();

    try {
        $this->assertNoCommittedRowsLeft();
    } catch (RuntimeException) {
        // The first one is the report.
    }

    // Same rows, second look: silent, so one leak is one failure and not 380.
    $this->assertNoCommittedRowsLeft();

    expect(true)->toBeTrue();
});
