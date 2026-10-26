<?php

declare(strict_types=1);

use App\Actions\Business\WriteBusinessBio;
use App\Actions\Catalog\WriteMissingDescriptions;
use App\Ai\Agents\BusinessBioWriter;
use App\Ai\Agents\CatalogCopywriter;
use App\Classes\Main\AiOptimizeLimit;
use App\Models\AiUsage;
use App\Models\Business;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PlatformSettingSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| "Optimizar con IA" has a daily ceiling, one for every screen and the app
|--------------------------------------------------------------------------
| The platform pays for these calls. The limit counts what the meter really
| recorded for the business today, across the identity card, services and
| products, and the Actions refuse to call the model once it is reached.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->business = Business::factory()->create(['name' => 'Clínica Vida']);
});

function spendOptimizeUses(Business $business, int $times, string $agent = 'BusinessBioWriter'): void
{
    // Not range(1, $times): with zero it counts backwards and would spend two.
    for ($i = 0; $i < $times; $i++) {
        AiUsage::query()->create([
            'business_id' => $business->id,
            'kind' => $agent,
            'model' => 'gpt-6-luna',
            'input_tokens' => 0,
            'cached_tokens' => 0,
            'output_tokens' => 0,
        ]);
    }
}

test('the ceiling is ten a day and an edit in Ajustes moves it', function (): void {
    expect(AiOptimizeLimit::for($this->business)->cap)->toBe(10);

    config()->set('atendia.ai_optimize.daily_uses', 3);

    expect(AiOptimizeLimit::for($this->business)->cap)->toBe(3);
});

test('the setting exists in Ajustes with the same default the file has', function (): void {
    $this->seed(PlatformSettingSeeder::class);

    $row = PlatformSetting::query()->where('key', 'ai_optimize.daily_uses')->first();

    expect($row)->not->toBeNull()
        ->and((int) $row->default_value)->toBe((int) config('atendia.ai_optimize.daily_uses'))
        ->and(__('admin.settings.groups.ai_optimize.title'))->not->toStartWith('admin.');
});

test('it counts the three screens together, only this business and only today', function (): void {
    $other = Business::factory()->create();

    spendOptimizeUses($this->business, 2, 'BusinessBioWriter');
    spendOptimizeUses($this->business, 1, 'CatalogCopywriter');
    spendOptimizeUses($this->business, 4, 'AsistenteAtendia');
    spendOptimizeUses($other, 5, 'BusinessBioWriter');

    AiUsage::query()->where('business_id', $this->business->id)->where('kind', 'BusinessBioWriter')->first()
        ->forceFill(['created_at' => now()->subDays(2)])->save();

    expect($this->business->aiOptimizeUsesToday())->toBe(2)
        ->and(AiOptimizeLimit::for($this->business)->used)->toBe(2)
        ->and(AiOptimizeLimit::for($this->business)->reached)->toBeFalse();
});

test('the identity writer stops calling the model at the ceiling', function (): void {
    BusinessBioWriter::fake()->preventStrayPrompts();
    config()->set('atendia.ai_optimize.daily_uses', 2);
    spendOptimizeUses($this->business, 2);

    expect(app(WriteBusinessBio::class)->handle($this->business))->toBeNull();

    // The Action swallows a blocked prompt as a failure, so null alone proves nothing: the model must never be asked.
    BusinessBioWriter::assertNeverPrompted();
});

test('one use below the ceiling still writes', function (): void {
    BusinessBioWriter::fake([['description' => 'Clínica Vida atiende odontología general.']]);
    config()->set('atendia.ai_optimize.daily_uses', 2);
    spendOptimizeUses($this->business, 1);

    expect(app(WriteBusinessBio::class)->handle($this->business))->toBe('Clínica Vida atiende odontología general.');
});

test('the catalog writer stops calling the model at the ceiling, but a catalog with nothing missing still says zero', function (): void {
    CatalogCopywriter::fake()->preventStrayPrompts();
    config()->set('atendia.ai_optimize.daily_uses', 1);
    spendOptimizeUses($this->business, 1, 'CatalogCopywriter');

    $blank = Service::factory()->for($this->business)->create(['description' => null]);
    $written = Service::factory()->for($this->business)->create(['description' => 'Ya tiene texto']);

    expect(app(WriteMissingDescriptions::class)->handle($this->business, collect([$blank])))->toBeNull()
        ->and(app(WriteMissingDescriptions::class)->handle($this->business, collect([$written])))->toBe(0);

    CatalogCopywriter::assertNeverPrompted();
});

test('the counter is calm, turns to a warning from eight of ten and says so once it is spent', function (int $spent, string $state, string $sentence): void {
    spendOptimizeUses($this->business, $spent);

    $limit = AiOptimizeLimit::for($this->business);

    expect($limit->state)->toBe($state)
        ->and($limit->left)->toBe(10 - $spent)
        ->and($limit->note)->toBe($sentence);
})->with([
    'untouched' => [0, 'ok', 'Te quedan 10 de 10 usos hoy.'],
    'seven spent' => [7, 'ok', 'Te quedan 3 de 10 usos hoy.'],
    'eight spent' => [8, 'warn', 'Te quedan solo 2 de 10 usos hoy.'],
    'nine spent' => [9, 'warn', 'Te queda 1 de 10 usos hoy: es el último.'],
    'ten spent' => [10, 'over', 'Hoy no te quedan usos de «Optimizar con IA». Mañana vuelves a tener 10.'],
]);

test('every screen shows the counter beside the offer', function (string $screen): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    spendOptimizeUses($this->business, 8);

    $user = User::factory()->create();
    $user->business()->associate($this->business)->save();

    Service::factory()->for($this->business)->create(['description' => null]);
    Product::factory()->for($this->business)->create(['description' => null]);

    Livewire::actingAs($user)
        ->test($screen)
        ->assertSeeHtml('data-testid="ai-banner-note"')
        ->assertSee('Te quedan solo 2 de 10 usos hoy.');
})->with(['client.section-identity', 'goods.index', 'products.index']);

test('a banner that is not metered prints no counter', function (): void {
    $html = Blade::render('<x-ui.ai-banner title="T" body="B" />');

    expect($html)->not->toContain('ai-banner-note');
});

test('every screen says the ceiling in the same words and never calls the model', function (string $screen, string $action, string $agent): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $agent::fake()->preventStrayPrompts();
    config()->set('atendia.ai_optimize.daily_uses', 1);
    spendOptimizeUses($this->business, 1);

    $user = User::factory()->create();
    $user->business()->associate($this->business)->save();

    Service::factory()->for($this->business)->create(['description' => null]);
    Product::factory()->for($this->business)->create(['description' => null]);

    Livewire::actingAs($user)
        ->test($screen)
        ->call($action)
        ->assertDispatched('notify', fn (string $event, array $params): bool => str_contains(
            json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'Hoy ya usaste «Optimizar con IA» 1 veces',
        ));

    $agent::assertNeverPrompted();
})->with([
    'identity' => ['client.section-identity', 'writeBio', BusinessBioWriter::class],
    'services' => ['goods.index', 'writeDescriptions', CatalogCopywriter::class],
    'products' => ['products.index', 'writeDescriptions', CatalogCopywriter::class],
]);
