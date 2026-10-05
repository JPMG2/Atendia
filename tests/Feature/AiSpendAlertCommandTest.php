<?php

declare(strict_types=1);

use App\Mail\AiSpendAlert;
use App\Models\AiModel;
use App\Models\AiUsage;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| atendia:ai-alert
|--------------------------------------------------------------------------
| The screen only tells her when she opens it, and by then the month is
| spent. The threshold rides the PLAN: the same 40 USD of AI is a rounding
| error on Premium and a loss on the floor plan.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PlanSeeder::class);

    config(['atendia.admin_email' => 'duena@atendia.test']);

    $admin = User::factory()->create(['email' => 'duena@atendia.test']);
    $admin->assignRole('admin');

    AiModel::create([
        'provider' => 'openai', 'code' => 'gpt-6-astra', 'label' => 'GPT-6 Astra',
        'effective_from' => now()->startOfMonth()->subYear(),
        'prompt_per_million' => 10, 'cached_per_million' => 1, 'completion_per_million' => 50,
    ]);
});

function businessOnPlan(string $name, string $plan, int $inputTokens): Business
{
    $business = Business::factory()->create(['name' => $name]);

    Subscription::factory()->create(['business_id' => $business->id, 'plan' => $plan]);

    AiUsage::query()->create([
        'business_id' => $business->id, 'kind' => 'AsistenteAtendia', 'model' => 'gpt-6-astra',
        'input_tokens' => $inputTokens, 'cached_tokens' => 0, 'output_tokens' => 0,
    ]);

    return $business;
}

test('it says nothing when no business is over its threshold', function (): void {
    Mail::fake();

    // 1M input tokens at 10 USD/M = 10 USD, well under 35% of 79.
    businessOnPlan('Laboratorio Vida', 'negocio', 1_000_000);

    $this->artisan('atendia:ai-alert')
        ->expectsOutputToContain('No business is over its plan threshold')
        ->assertSuccessful();

    Mail::assertNothingQueued();
});

test('it mails the admin the business whose ai ate its plan', function (): void {
    Mail::fake();

    // 4M tokens at 10 USD/M = 40 USD against a 29 USD plan: 138% of it.
    businessOnPlan('Kiosco La Esquina', 'emprende', 4_000_000);

    $this->artisan('atendia:ai-alert')->assertSuccessful();

    // Queued, not sent: the Mailable is ShouldQueue, so the channel hands it
    // to the queue and returns.
    Mail::assertQueued(AiSpendAlert::class, function (AiSpendAlert $mail): bool {
        return $mail->hasTo('duena@atendia.test')
            && str_contains(implode(' ', $mail->lines()), 'Kiosco La Esquina');
    });
});

test('the same spend is over the floor plan and not over the top one', function (): void {
    Mail::fake();

    // 20 USD of AI: 69% of the 29 USD plan, 13% of the 149 USD one.
    businessOnPlan('Kiosco La Esquina', 'emprende', 2_000_000);
    businessOnPlan('Centro Odontológico', 'premium', 2_000_000);

    $this->artisan('atendia:ai-alert')->assertSuccessful();

    Mail::assertQueued(AiSpendAlert::class, function (AiSpendAlert $mail): bool {
        $body = implode(' ', $mail->lines());

        return str_contains($body, 'Kiosco La Esquina')
            && ! str_contains($body, 'Centro Odontológico');
    });
});

test('with no admin account it sends nothing instead of failing', function (): void {
    Mail::fake();

    config(['atendia.admin_email' => 'nadie@atendia.test']);
    businessOnPlan('Kiosco La Esquina', 'emprende', 4_000_000);

    $this->artisan('atendia:ai-alert')
        ->expectsOutputToContain('No admin account')
        ->assertSuccessful();

    Mail::assertNothingQueued();
});
