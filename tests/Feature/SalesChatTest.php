<?php

declare(strict_types=1);

use App\Classes\Main\Plan;
use App\Models\SalesChatMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the landing draws the sales chat with every plan price and the hand-off to sales', function (): void {
    config()->set('atendia.sales_whatsapp', '5492995529100');

    $response = $this->get('/')
        ->assertOk()
        ->assertSee('data-testid="sales-chat"', false)
        ->assertSee(__('landing.sales_chat.quick.prices'))
        ->assertSee(__('landing.sales_chat.quick.person'))
        ->assertSee('wa.me/5492995529100?text='.rawurlencode(__('landing.sales_chat.whatsapp_text')), false);

    foreach (Plan::ladder() as $plan) {
        $response->assertSee(config('atendia.billing.currency_symbol').$plan->price);
    }
});

test('without a sales number the chat draws no person chip and no WhatsApp button', function (): void {
    config()->set('atendia.sales_whatsapp', null);

    $this->get('/')
        ->assertSee('data-testid="sales-chat"', false)
        ->assertDontSee(__('landing.sales_chat.quick.person'))
        ->assertDontSee('data-testid="sales-chat-whatsapp"', false);
});

test('phase one draws no free-text box: nothing typed can reach a model', function (): void {
    $html = $this->get('/')->getContent();
    $chat = substr($html, strpos($html, 'data-testid="sales-chat"'));

    expect(substr($chat, 0, strpos($chat, 'data-testid="sales-chat-open"')))->not->toContain('<input', '<textarea');
});

test('each event bumps today\'s counter and an open counts once per session', function (): void {
    $this->postJson(route('sales-chat.event'), ['event' => 'open'])->assertNoContent();
    $this->postJson(route('sales-chat.event'), ['event' => 'open'])->assertNoContent();
    $this->postJson(route('sales-chat.event'), ['event' => 'answer'])->assertNoContent();
    $this->postJson(route('sales-chat.event'), ['event' => 'answer'])->assertNoContent();
    $this->postJson(route('sales-chat.event'), ['event' => 'handoff'])->assertNoContent();

    $today = SalesChatMetric::query()->sole();

    expect($today->opens)->toBe(1)
        ->and($today->answers)->toBe(2)
        ->and($today->handoffs)->toBe(1);
});

test('an unknown event is refused and stores nothing', function (): void {
    $this->postJson(route('sales-chat.event'), ['event' => 'message', 'text' => 'hola'])->assertUnprocessable();

    expect(SalesChatMetric::query()->count())->toBe(0);
});

test('the counter is throttled per visitor', function (): void {
    foreach (range(1, 20) as $ignored) {
        $this->postJson(route('sales-chat.event'), ['event' => 'answer'])->assertNoContent();
    }

    $this->postJson(route('sales-chat.event'), ['event' => 'answer'])->assertTooManyRequests();
});
