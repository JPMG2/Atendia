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
    foreach (range(1, $times) as $ignored) {
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
