<?php

declare(strict_types=1);

use App\Classes\Main\AiSpend;
use App\Enums\SubscriptionStatus;
use App\Models\AiConnection;
use App\Models\AiModel;
use App\Models\AiUsage;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\RevenueSnapshot;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Consumo de IA (admin)
|--------------------------------------------------------------------------
| The one screen that answers whether a client of 29 USD is eating 40. It
| adds nothing of its own: it reads the meter and values it at the price the
| model had THAT month, and says so when there is no published price —
| because a zero there would read as "that client was free".
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

function spendAdmin(): User
{
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    return tap($admin->refresh(), fn (User $user) => test()->actingAs($user));
}

function astraPriced(float $prompt = 2.0): AiModel
{
    return AiModel::create([
        'provider' => 'openai', 'code' => 'gpt-6-astra', 'label' => 'GPT-6 Astra',
        'effective_from' => now()->startOfMonth()->subYear(),
        'prompt_per_million' => $prompt, 'cached_per_million' => 0.5, 'completion_per_million' => 10.0,
    ]);
}

test('the screen opens for the admin and closes for a client', function (): void {
    spendAdmin();
    $this->get(route('admin.ai-usage'))->assertOk();

    Auth::logout();
    $client = User::factory()->create(['email_verified_at' => now()]);
    $client->assignRole('client');
    $client->business()->associate(Business::factory()->create())->save();

    $this->actingAs($client->refresh())->get(route('admin.ai-usage'))->assertForbidden();
});

test('the screen renders with nothing metered instead of breaking', function (): void {
    spendAdmin();

    Livewire::test('admin.ai-usage.index')
        ->assertOk()
        ->assertSee('Nada medido en este mes');
});

test('the month is also cut by the key each call went through, and old calls stay apart', function (): void {
    spendAdmin();
    astraPriced();
    AiConnection::create(['key' => 'openai-app', 'label' => 'OpenAI · app']);
    AiConnection::create(['key' => 'openai', 'label' => 'OpenAI · general']);

    // $2 of input on one key, $4 on the other, and $2 from before the key was recorded.
    foreach ([['openai-app', 1_000_000], ['openai', 2_000_000], [null, 1_000_000]] as [$key, $input]) {
        AiUsage::query()->create([
            'business_id' => Business::factory()->create()->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra',
            'connection_key' => $key, 'input_tokens' => $input,
        ]);
    }

    Livewire::test('admin.ai-usage.index')
        ->assertSee('Desglose por clave')
        ->assertSee('OpenAI · app')
        ->assertSee('OpenAI · general')
        ->assertSee('Sin clave registrada')
        ->assertSee('$4,00');
});

test('each business is billed at the price its model had that month', function (): void {
    spendAdmin();
    astraPriced();

    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);

    // $2 of input + $1 of cached + $1 of output.
    AiUsage::query()->create([
        'business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra',
        'input_tokens' => 1_000_000, 'cached_tokens' => 2_000_000, 'output_tokens' => 100_000,
    ]);

    Livewire::test('admin.ai-usage.index')
        ->assertSee('Laboratorio Vida')
        ->assertSee('$4,00')
        ->assertSee('AsistenteAtendia');
});

test('a call with no published price shows no cost instead of a zero', function (): void {
    spendAdmin();

    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);

    AiUsage::query()->create([
        'business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'model' => 'modelo-sin-precio',
        'input_tokens' => 1_000_000, 'output_tokens' => 100_000,
    ]);

    Livewire::test('admin.ai-usage.index')
        ->assertSee('Laboratorio Vida')
        ->assertSee('su modelo no tiene precio publicado')
        // Revenue may be a real zero (nobody paying); cost and margin never are.
        ->assertSeeInOrder(['Costo de la IA', '—'])
        ->assertSeeInOrder(['Margen sobre la IA', '—']);
});

test('the month shows revenue, AI cost and the margin left, from the same rule as the MRR', function (): void {
    spendAdmin();
    astraPriced();

    $payer = Business::factory()->create(['name' => 'Laboratorio Vida']);
    Subscription::query()->where('business_id', $payer->id)->delete();
    Subscription::factory()->create(['business_id' => $payer->id, 'plan' => 'negocio', 'status' => SubscriptionStatus::Active]);

    // $2 of input, on the plan's month price.
    AiUsage::query()->create([
        'business_id' => $payer->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra', 'input_tokens' => 1_000_000,
    ]);

    $monthly = Subscription::monthlyRecurringRevenue();

    expect($monthly)->toBeGreaterThan(2.0);

    Livewire::test('admin.ai-usage.index')
        ->assertSee('Ingresos del mes')
        ->assertSee('$'.number_format($monthly, 2, ',', '.'))
        ->assertSee('$'.number_format($monthly - 2.0, 2, ',', '.'))
        ->assertSee('Deja $'.number_format($monthly - 2.0, 2, ',', '.'));
});

test('the net result needs the fixed costs: unloaded it points to the setting, loaded it subtracts them', function (): void {
    spendAdmin();
    astraPriced();
    $payer = Business::factory()->create();
    Subscription::query()->where('business_id', $payer->id)->delete();
    Subscription::factory()->create(['business_id' => $payer->id, 'plan' => 'negocio', 'status' => SubscriptionStatus::Active]);
    AiUsage::query()->create(['business_id' => $payer->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra', 'input_tokens' => 1_000_000]);

    // Zero is "not loaded", never "free": no net, and the tile is the way to the setting.
    expect(AiSpend::of(now())->result->net)->toBeNull();
    Livewire::test('admin.ai-usage.index')->assertSee('Cargá los costos fijos del mes');

    config(['atendia.costs.fixed_monthly_usd' => 5]);
    $margin = Subscription::monthlyRecurringRevenue() - 2.0;

    expect(AiSpend::of(now())->result->net)->toEqualWithDelta($margin - 5, 0.0001);
    Livewire::test('admin.ai-usage.index')
        ->assertSee('Resultado neto')
        ->assertSee('El margen menos $5,00 de costos fijos');
});

test('an earlier month is never netted against today fixed costs', function (): void {
    config(['atendia.costs.fixed_monthly_usd' => 5]);

    expect(AiSpend::of(now()->subMonthNoOverflow())->result->fixed)->toBeNull();
});

test('a business that costs more than it pays is marked as a loss, and a trial is not', function (): void {
    spendAdmin();
    astraPriced(prompt: 10_000.0);

    $loser = Business::factory()->create(['name' => 'Taller Caro']);
    $trial = Business::factory()->create(['name' => 'Recién llegado']);
    Subscription::query()->whereIn('business_id', [$loser->id, $trial->id])->delete();
    Subscription::factory()->create(['business_id' => $loser->id, 'plan' => 'negocio', 'status' => SubscriptionStatus::Active]);
    Subscription::factory()->create(['business_id' => $trial->id, 'plan' => 'negocio', 'status' => SubscriptionStatus::Trialing]);

    foreach ([$loser, $trial] as $business) {
        AiUsage::query()->create(['business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra', 'input_tokens' => 1_000_000]);
    }

    Livewire::test('admin.ai-usage.index')
        ->assertSee('Pierde')
        ->assertSee('En prueba')
        ->assertSee('lo que cuesta es el precio de ganar al cliente');
});

test('an earlier month with no revenue photo has no margin instead of a revenue of zero', function (): void {
    spendAdmin();
    astraPriced();
    $business = Business::factory()->create();

    AiUsage::query()->create(['business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra', 'input_tokens' => 1_000_000])
        ->forceFill(['created_at' => now()->subMonthNoOverflow()])->save();

    $result = AiSpend::of(now()->subMonthNoOverflow())->result;

    expect($result->revenue)->toBeNull()
        ->and($result->cost)->toEqualWithDelta(2.0, 0.0001)
        ->and($result->margin)->toBeNull();

    Livewire::test('admin.ai-usage.index')
        ->set('month', now()->subMonthNoOverflow()->format('Y-m'))
        ->assertSee('Sin registro de ingresos de ese mes');
});

test('an earlier month with a revenue photo reads its margin from it', function (): void {
    spendAdmin();
    astraPriced();
    $business = Business::factory()->create();
    $earlier = now()->subMonthNoOverflow();

    RevenueSnapshot::query()->create(['month' => $earlier->copy()->startOfMonth()->toDateString(), 'mrr' => 100, 'paying' => 3, 'trialing' => 0, 'gained' => 0, 'lost' => 0]);
    AiUsage::query()->create(['business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra', 'input_tokens' => 1_000_000])
        ->forceFill(['created_at' => $earlier])->save();

    $result = AiSpend::of($earlier)->result;

    expect($result->revenue)->toBe(100.0)
        ->and($result->margin)->toEqualWithDelta(98.0, 0.0001)
        ->and($result->share)->toEqualWithDelta(0.98, 0.0001);
});

test('the month bill includes the audio minutes, like each row above it does', function (): void {
    astraPriced();
    $business = Business::factory()->create();
    $conversation = Conversation::factory()->create(['business_id' => $business->id]);

    // Ten minutes of voice notes: a business with metered tokens AND audio.
    ConversationMessage::factory()->create(['business_id' => $business->id, 'conversation_id' => $conversation->id, 'audio_seconds' => 600]);
    AiUsage::query()->create(['business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra', 'input_tokens' => 1_000_000]);

    $spend = AiSpend::of(now());
    $audioCost = 10 * (float) config('atendia.ai_rates.audio_per_minute');

    expect($spend->audioSeconds)->toBe(600)
        ->and($spend->bill->cost)->toEqualWithDelta(2.0 + $audioCost, 0.0001)
        // The row says the same as the total: they are one reading of the month.
        ->and($spend->board->first()->totals->cost)->toEqualWithDelta($spend->bill->cost, 0.0001);
});

test('a call with no business is charged to the platform, not to a client', function (): void {
    spendAdmin();
    astraPriced();

    AiUsage::query()->create([
        'business_id' => null, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra',
        'input_tokens' => 1_000_000, 'output_tokens' => 0,
    ]);

    Livewire::test('admin.ai-usage.index')
        ->assertSee('Plataforma')
        ->assertSee('$2,00');
});

test('clearing the month picker falls back to this month instead of breaking', function (): void {
    spendAdmin();
    astraPriced();

    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);

    AiUsage::query()->create([
        'business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra',
        'input_tokens' => 1_000_000, 'output_tokens' => 0,
    ]);

    Livewire::test('admin.ai-usage.index')
        ->set('month', '')
        ->assertOk()
        ->assertSee('Laboratorio Vida');
});

test('the figures are written the way this app writes every number', function (): void {
    spendAdmin();
    astraPriced();

    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);

    AiUsage::query()->create([
        'business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra',
        'input_tokens' => 1_200_000, 'output_tokens' => 0,
    ]);

    // A thousands separator read in English beside a price read in Spanish is
    // two number systems on one row.
    Livewire::test('admin.ai-usage.index')
        ->assertSee('1.200.000')
        ->assertDontSee('1,200,000');
});

test('choosing another month changes what is counted', function (): void {
    spendAdmin();
    astraPriced();

    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);

    AiUsage::query()->create([
        'business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra',
        'input_tokens' => 1_000_000, 'output_tokens' => 0,
    ]);

    Livewire::test('admin.ai-usage.index')
        ->assertSee('$2,00')
        ->set('month', now()->startOfMonth()->subMonth()->format('Y-m'))
        ->assertSee('Nada medido en este mes')
        ->assertDontSee('Laboratorio Vida');
});
