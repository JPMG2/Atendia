<?php

declare(strict_types=1);

use App\Actions\Billing\ApprovePayment;
use App\Ai\Agents\AsistenteAtendia;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Mail\BillingPaymentReviewed;
use App\Mail\BillingReminder;
use App\Models\Business;
use App\Models\Company;
use App\Models\ConversationMessage;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| "Mis pagos" — what a business owes Atendia and what it paid
|--------------------------------------------------------------------------
| Payment-rail agnostic: the client uploads a transfer receipt, the admin
| credits or rejects it. The daily pass reminds at 10 and 5 days, walks a
| lapsed period through 5 grace days and pauses the assistant after them.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    // The billing pass runs at each business's local 09:00 (UTC here: no country set).
    $this->travelTo(now()->setTime(9, 5));
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
    Storage::fake('local');
    config()->set('services.evolution.url', 'http://evolution.test');
    config()->set('services.evolution.key', 'test-key');
    config()->set('services.evolution.instance', 'atendia');
    Http::fake(['http://evolution.test/*' => Http::response(['status' => 'PENDING'])]);
});

function billingClient(array $business = []): User
{
    $owner = Business::factory()->create(array_merge([
        // A fixed clock: the factory picks a random country, and the pass runs at LOCAL 09:00.
        'timezone' => 'UTC',
        'billing_email' => 'pagos@shop.test',
        'fallback_whatsapp_number' => '5491122334455',
    ], $business));

    return User::factory()->create(['business_id' => $owner->id])->refresh();
}

function dueIn(User $user, int $days, SubscriptionStatus $status = SubscriptionStatus::Active): void
{
    $user->business->subscription->forceFill([
        'status' => $status,
        'current_period_ends_at' => now()->addDays($days),
    ])->save();
}

test('mis pagos sits behind the client panel lock and the admin desk behind the admin one', function (): void {
    $this->get(route('my-payments'))->assertRedirect(route('login'));

    $this->actingAs(billingClient())
        ->get(route('my-payments'))->assertSuccessful()->assertSee(__('billing.title'));

    $this->get(route('admin.payments'))->assertForbidden();
});

test('the page reads the running period, the next payment and how to pay', function (): void {
    Company::factory()->create(['payment_instructions' => "Banco Nación\nAlias: atendia.pagos"]);
    $user = billingClient();
    dueIn($user, 5);
    $this->actingAs($user);

    livewire('payments.index')
        ->assertSee(__('billing.period.title'))
        ->assertSee('USD 79,00')
        ->assertSee('atendia.pagos')
        ->assertSee(trans_choice('billing.period.left_days', 5))
        ->assertSee(__('billing.history.empty'));
});

test('a receipt is stored privately under the tenant and waits for review, one at a time', function (): void {
    $user = billingClient();
    $this->actingAs($user);

    livewire('payments.index')
        ->call('openUpload')
        ->set('form.receipt', UploadedFile::fake()->create('comprobante.pdf', 200, 'application/pdf'))
        ->set('form.reference', '00012345')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('uploading', false);

    $payment = Payment::query()->sole();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->reference)->toBe('00012345')
        ->and((float) $payment->amount)->toBe(79.0)
        ->and($payment->receipt_path)->toStartWith("businesses/{$user->business_id}/payments/");
    Storage::disk('local')->assertExists($payment->receipt_path);

    livewire('payments.index')
        ->call('openUpload')
        ->set('form.receipt', UploadedFile::fake()->create('otro.pdf', 100, 'application/pdf'))
        ->call('submit')
        ->assertDispatched('notify', type: 'warning');

    expect(Payment::query()->count())->toBe(1);
});

test('a receipt must be an image or a pdf', function (): void {
    $this->actingAs(billingClient());

    livewire('payments.index')
        ->call('openUpload')
        ->set('form.receipt', UploadedFile::fake()->create('virus.exe', 10))
        ->call('submit')
        ->assertHasErrors('receipt');
});

test('a receipt is served to its own business only', function (): void {
    $user = billingClient();
    Storage::disk('local')->put('businesses/x/payments/r.pdf', 'pdf');
    $payment = Payment::factory()->create(['business_id' => $user->business_id, 'receipt_path' => 'businesses/x/payments/r.pdf']);
    $stranger = billingClient();

    $this->actingAs($user)->get(route('my-payments.receipt', $payment))->assertSuccessful();
    $this->actingAs($stranger)->get(route('my-payments.receipt', $payment))->assertNotFound();
});

test('crediting a payment continues the period and mails the client', function (): void {
    $user = billingClient();
    dueIn($user, 3);
    $end = $user->business->subscription->current_period_ends_at;
    $payment = Payment::factory()->create(['business_id' => $user->business_id, 'subscription_id' => $user->business->subscription->id]);
    $admin = User::factory()->create();

    app(ApprovePayment::class)->handle($payment, $admin);

    $subscription = $user->business->subscription->refresh();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->period_starts_at->equalTo($end))->toBeTrue()
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->current_period_ends_at->equalTo($end->copy()->addMonth()))->toBeTrue();

    Mail::assertQueued(BillingPaymentReviewed::class, fn ($mail): bool => $mail->hasTo('pagos@shop.test'));
});

test('crediting a paused business wakes the assistant from today', function (): void {
    $user = billingClient();
    dueIn($user, -8, SubscriptionStatus::Paused);
    $user->business->subscription->forceFill(['paused_at' => now()->subDays(3)])->save();
    $payment = Payment::factory()->create(['business_id' => $user->business_id, 'subscription_id' => $user->business->subscription->id]);

    app(ApprovePayment::class)->handle($payment, User::factory()->create());

    $subscription = $user->business->subscription->refresh();

    expect($subscription->isPaused())->toBeFalse()
        ->and($subscription->daysUntilPayment())->toBeGreaterThanOrEqual(28);
});

test('the admin desk credits and rejects with a reason the client reads', function (): void {
    $user = billingClient();
    $credited = Payment::factory()->create(['business_id' => $user->business_id, 'subscription_id' => $user->business->subscription->id]);
    $rejected = Payment::factory()->create(['business_id' => $user->business_id]);
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    $this->actingAs($admin->refresh());

    livewire('admin.payments')
        ->assertSee($user->business->name)
        ->call('approve', $credited->id)
        ->call('startReject', $rejected->id)
        ->call('reject')
        ->assertHasErrors('reason')
        ->set('form.reason', 'El comprobante no se lee')
        ->call('reject')
        ->assertHasNoErrors();

    expect($credited->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($rejected->refresh()->status)->toBe(PaymentStatus::Rejected)
        ->and($rejected->rejection_reason)->toBe('El comprobante no se lee');
});

test('the owner is reminded at 10 and 5 days, once each, by mail and whatsapp', function (int $days, bool $reminded): void {
    $user = billingClient();
    dueIn($user, $days);

    $this->artisan('atendia:billing-cycle');
    $this->artisan('atendia:billing-cycle');

    if ($reminded) {
        Mail::assertQueued(BillingReminder::class, 1);
        Mail::assertQueued(BillingReminder::class, fn ($mail): bool => $mail->stage === 'upcoming' && $mail->days === $days && $mail->hasTo('pagos@shop.test'));
        Http::assertSent(fn (Request $request): bool => $request['number'] === '5491122334455' && str_contains((string) $request['text'], "en {$days} días"));
    } else {
        Mail::assertNothingQueued();
    }
})->with([
    'ten days' => [10, true],
    'five days' => [5, true],
    'seven days' => [7, false],
]);

test('every business due gets its reminder when several are billed in one pass', function (): void {
    // Lazy-loading protection only fires when the models came in a batch:
    // one business passed, two in production broke the whatsapp line (2026-09-24).
    foreach (['5491122334455', '5491166778899'] as $phone) {
        dueIn(billingClient(['fallback_whatsapp_number' => $phone]), 10);
    }

    $this->artisan('atendia:billing-cycle')->assertSuccessful();

    foreach (['5491122334455', '5491166778899'] as $phone) {
        Http::assertSent(fn (Request $request): bool => $request['number'] === $phone && str_contains((string) $request['text'], 'en 10 días'));
    }
    Mail::assertQueued(BillingReminder::class, 2);
});

test('a lapsed period gets grace days, reminded daily, and then pauses the assistant', function (): void {
    $user = billingClient();
    dueIn($user, -1);

    $this->artisan('atendia:billing-cycle');

    $subscription = $user->business->subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::PastDue);
    Mail::assertQueued(BillingReminder::class, fn ($mail): bool => $mail->stage === 'overdue' && $mail->days === 4);

    dueIn($user, -5, SubscriptionStatus::PastDue);
    $this->artisan('atendia:billing-cycle');

    expect($user->business->subscription->refresh()->isPaused())->toBeTrue();
    Mail::assertQueued(BillingReminder::class, fn ($mail): bool => $mail->stage === 'paused');
});

test('a receipt under review freezes reminders and the pause', function (): void {
    $user = billingClient();
    dueIn($user, -6, SubscriptionStatus::PastDue);
    Payment::factory()->create(['business_id' => $user->business_id]);

    $this->artisan('atendia:billing-cycle');

    expect($user->business->subscription->refresh()->isPaused())->toBeFalse();
    Mail::assertNothingQueued();
});

test('a paused assistant keeps the record but never answers', function (): void {
    $business = Business::factory()->create(['whatsapp_instance' => 'demo']);
    $business->subscription->forceFill(['status' => SubscriptionStatus::Paused, 'paused_at' => now()])->save();
    AsistenteAtendia::fake(['Nunca debería salir.', 'Nunca debería salir.']);

    (new ProcessIncomingWhatsAppMessage('demo', '5491100000000', 'Carla', '¿Abren hoy?', 'MSG-1'))->handle();

    expect(ConversationMessage::query()->count())->toBe(1);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/message/sendText/demo'));
});

test('the panel shows the reminder banner and the menu badge five days out', function (): void {
    $this->seed(MenuSeeder::class);
    $user = billingClient();
    dueIn($user, 5);
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertSee(trans_choice('billing.banner.upcoming', 5))
        ->assertSee(trans_choice('billing.badge', 5))
        ->assertSee(__('menu.plan_payments'));
});

test('a business in its grace days keeps its paid plan', function (): void {
    $user = billingClient();
    $user->business->subscription->forceFill(['plan' => 'premium', 'trial_ends_at' => now()->subMonth()])->save();
    dueIn($user, -2, SubscriptionStatus::PastDue);

    expect($user->business->refresh()->plan()->code)->toBe('premium');
});
