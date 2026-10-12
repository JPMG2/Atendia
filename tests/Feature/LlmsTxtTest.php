<?php

declare(strict_types=1);

use App\Classes\Main\Plan;
use App\Models\Company;
use App\Models\SubscriptionPlan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlanSeeder::class);
});

test('llms.txt is plain text built from the real plans and the brand', function (): void {
    $response = $this->get('/llms.txt')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/plain');

    $body = $response->getContent();

    expect($body)->toStartWith('# '.Company::brand())
        ->and($body)->toContain('## Planes y precios')
        ->and($body)->toContain(route('register'));

    // Every plan on sale, with the price it really has: nothing typed by hand.
    foreach (Plan::ladder() as $plan) {
        expect($body)->toContain(__('plan.names.'.$plan->code))
            ->and($body)->toContain('$'.$plan->price);
    }
});

test('a plan taken off sale stops appearing in llms.txt', function (): void {
    $archived = collect(Plan::everyPlan())->last();
    SubscriptionPlan::query()->where('code', $archived->code)->update(['archived_at' => now()]);
    Cache::flush();

    $this->get('/llms.txt')->assertOk()->assertDontSee(__('plan.names.'.$archived->code).':', false);
});

test('llms.txt speaks neutral Spanish whatever locale the visitor has', function (): void {
    // The Argentine hero says "hacés lo tuyo"; the neutral one "haces lo tuyo".
    $this->withSession(['locale' => 'es_AR'])->get('/llms.txt')
        ->assertOk()
        ->assertSee('mientras haces lo tuyo')
        ->assertDontSee('mientras hacés lo tuyo');
});
