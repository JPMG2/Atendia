<?php

declare(strict_types=1);

use App\Classes\Main\Plan;
use App\Models\SalesChatMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The landing's sales chat, in the flesh
|--------------------------------------------------------------------------
| Phase 1: quick answers drawn in the page, a hand-off to the sales WhatsApp
| and daily counters. No model is called and nothing typed is stored.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    config()->set('atendia.sales_whatsapp', '5492995529100');
});

test('a quick answer opens with the real prices and both the open and the answer are counted', function (): void {
    $page = visit('/')->resize(1280, 900);

    $page->assertNoJavaScriptErrors();
    $page->script("document.querySelector('[data-testid=sales-chat-open]').click()");
    $page->wait(0.4);
    $page->script("document.querySelector('.sales-chat-chip').click()");
    $page->wait(0.8);

    $answer = $page->script("document.querySelector('[data-testid=sales-chat-answer]')?.innerText ?? ''");

    foreach (Plan::ladder() as $plan) {
        expect($answer)->toContain(config('atendia.billing.currency_symbol').$plan->price);
    }

    $today = SalesChatMetric::query()->sole();

    expect($today->opens)->toBe(1)->and($today->answers)->toBe(1);
});

test('the welcome bubble waits for the hero to scroll away, shows once per visitor and opens the chat', function (): void {
    config()->set('atendia.sales_chat.nudge_seconds', 1);

    $page = visit('/')->resize(1280, 900);
    $page->wait(1.5);

    // Still on the hero: the bubble would cover the demo phone.
    expect($page->script("getComputedStyle(document.querySelector('[data-testid=sales-chat-nudge]')).display"))->toBe('none');

    $page->script('window.scrollTo(0, 2000)');
    $page->wait(0.8);

    expect($page->script("getComputedStyle(document.querySelector('[data-testid=sales-chat-nudge]')).display"))->not->toBe('none');

    $page->script("document.querySelector('.sales-chat-nudge-text').click()");
    $page->wait(0.4);

    expect($page->script("getComputedStyle(document.querySelector('[data-testid=sales-chat-panel]')).display"))->not->toBe('none')
        ->and($page->script("localStorage.getItem('atendia-sales-nudge')"))->toBe('1');

    // The same visitor coming back: a reload keeps the browser's storage (a new visit() would not).
    $page->script('location.reload()');
    $page->wait(1.5);
    $page->script('window.scrollTo(0, 2000)');
    $page->wait(1.8);

    expect($page->script("getComputedStyle(document.querySelector('[data-testid=sales-chat-nudge]')).display"))->toBe('none');
});

test('on a phone the chat fits the screen and leaves no horizontal scroll', function (): void {
    $page = visit('/')->resize(390, 844);

    $page->script("document.querySelector('[data-testid=sales-chat-open]').click()");
    $page->wait(0.4);

    $box = $page->script("(() => { const r = document.querySelector('[data-testid=sales-chat-panel]').getBoundingClientRect(); return [r.left, r.right, r.top]; })()");

    expect($box[0])->toBeGreaterThanOrEqual(0)
        ->and($box[1])->toBeLessThanOrEqual(390)
        ->and($box[2])->toBeGreaterThanOrEqual(0)
        ->and($page->script('document.documentElement.scrollWidth > innerWidth'))->toBeFalse();
});
