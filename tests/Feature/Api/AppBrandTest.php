<?php

declare(strict_types=1);

use App\Models\Company;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

it('tells anyone the platform name, as the row the web reads says it', function (): void {
    Company::factory()->create(['brand_name' => 'Conversa']);

    $this->getJson('/api/v1/brand')->assertOk()->assertExactJson(['data' => ['name' => Company::brand()]]);
});

it('falls back to the configured name while nobody has saved the company', function (): void {
    Company::query()->delete();

    $this->getJson('/api/v1/brand')->assertOk()->assertJsonPath('data.name', (string) config('app.name'));
});
