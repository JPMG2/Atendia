<?php

declare(strict_types=1);

use App\Actions\Billing\ApprovePayment;
use App\Actions\Billing\RejectPayment;
use App\Actions\Compliance\BackfillComplianceEvents;
use App\Actions\Compliance\RecordComplianceEvent;
use App\Actions\Moderation\LiftSuspension;
use App\Actions\Moderation\RecordModerationFlag;
use App\Actions\Moderation\SubmitSuspensionAppeal;
use App\Dto\ModerationVerdictDto;
use App\Enums\ComplianceEventKind;
use App\Enums\ModerationSeverity;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\ComplianceEvent;
use App\Models\ModerationFlag;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The file of a business
|--------------------------------------------------------------------------
| Suspending and lifting used to overwrite one column, so a business that was
| suspended twice kept no history. These pin that each thing that happens is
| written down when it happens — the fault AND the answer — and that the file
| reads the business as it is today, not as it was.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    Mail::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(MenuSeeder::class);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');
});

function kindsOf(Business $business): array
{
    return ComplianceEvent::query()
        ->where('business_id', $business->id)
        ->orderBy('id')
        ->get()
        ->map(fn (ComplianceEvent $event): string => $event->kind->value)
        ->all();
}

test('a refused file and a severe one are written down, and the severe one writes the suspension too', function (): void {
    $business = Business::factory()->create(['timezone' => 'UTC']);

    app(RecordModerationFlag::class)->handle($business, 'logo', 'image', new ModerationVerdictDto(ModerationSeverity::Rejected, 'sexual', 0.9), 'fp-1');
    app(RecordModerationFlag::class)->handle($business, 'product', 'image', new ModerationVerdictDto(ModerationSeverity::Severe, 'sexual/minors', 0.99), 'fp-2');

    expect(kindsOf($business))->toBe(['content_rejected', 'content_severe', 'suspended'])
        ->and(ComplianceEvent::query()->where('kind', 'content_rejected')->value('detail'))->toBe('logo · sexual');
});

test('the same file validated twice is one line, because it is one catch', function (): void {
    $business = Business::factory()->create();
    $verdict = new ModerationVerdictDto(ModerationSeverity::Rejected, 'sexual', 0.9);

    app(RecordModerationFlag::class)->handle($business, 'logo', 'image', $verdict, 'same');
    app(RecordModerationFlag::class)->handle($business, 'logo', 'image', $verdict, 'same');

    expect(kindsOf($business))->toBe(['content_rejected']);
});

test('the appeal survives the suspension being lifted, and lifting names who decided', function (): void {
    $business = Business::factory()->create();
    $business->forceFill(['suspended_at' => now(), 'suspension_reason' => 'sexual'])->save();

    app(SubmitSuspensionAppeal::class)->handle($business->refresh(), 'Es un catálogo de lencería, no de otra cosa.');
    app(LiftSuspension::class)->handle($business->id, $this->admin);

    $business->refresh();
    $appeal = ComplianceEvent::query()->where('kind', 'appealed')->sole();
    $lifted = ComplianceEvent::query()->where('kind', 'suspension_lifted')->sole();

    // The column was blanked; the file still has what they said and who answered.
    expect($business->appeal_message)->toBeNull()
        ->and($appeal->detail)->toBe('Es un catálogo de lencería, no de otra cosa.')
        ->and($lifted->actor_id)->toBe($this->admin->id)
        ->and($lifted->detail)->toBe('sexual');
});

test('a refused receipt and its regularization are both in the file', function (): void {
    $business = Business::factory()->create();
    $subscription = $business->subscription;
    $subscription->forceFill(['status' => SubscriptionStatus::PastDue, 'current_period_ends_at' => now()->subDay()])->save();

    $rejected = Payment::factory()->create(['business_id' => $business->id, 'subscription_id' => $subscription->id, 'status' => PaymentStatus::Pending]);
    app(RejectPayment::class)->handle($rejected, $this->admin, 'El monto no coincide');

    $paid = Payment::factory()->create(['business_id' => $business->id, 'subscription_id' => $subscription->id, 'status' => PaymentStatus::Pending]);
    app(ApprovePayment::class)->handle($paid, $this->admin);

    expect(kindsOf($business))->toBe(['payment_rejected', 'payment_regularized'])
        ->and(ComplianceEvent::query()->where('kind', 'payment_rejected')->value('detail'))->toBe('El monto no coincide');
});

test('an ordinary renewal is not a regularization', function (): void {
    $business = Business::factory()->create();
    $subscription = $business->subscription;
    $subscription->forceFill(['status' => SubscriptionStatus::Active, 'current_period_ends_at' => now()->addDays(3)])->save();

    $payment = Payment::factory()->create(['business_id' => $business->id, 'subscription_id' => $subscription->id, 'status' => PaymentStatus::Pending]);
    app(ApprovePayment::class)->handle($payment, $this->admin);

    expect(kindsOf($business))->toBe([]);
});

test('the billing pass writes the day a payment turned late once, and the pause when it comes', function (): void {
    $this->travelTo(now()->setTime(9, 5));
    $business = Business::factory()->create(['timezone' => 'UTC', 'billing_email' => 'pagos@shop.test']);
    $subscription = $business->subscription;

    $subscription->forceFill(['status' => SubscriptionStatus::Active, 'current_period_ends_at' => now()->subDay()])->save();
    $this->artisan('atendia:billing-cycle');
    // The next morning is still inside the grace days: the pass runs again, the file does not repeat.
    $this->artisan('atendia:billing-cycle');

    expect(kindsOf($business))->toBe(['payment_late']);

    $subscription->refresh()->forceFill(['status' => SubscriptionStatus::PastDue, 'current_period_ends_at' => now()->subDays(5)])->save();
    $this->artisan('atendia:billing-cycle');

    expect(kindsOf($business))->toBe(['payment_late', 'payment_paused']);
});

test('a failure to write the file never undoes what it was recording', function (): void {
    // A business that does not exist: the insert breaks, and the caller must not.
    $written = app(RecordComplianceEvent::class)->handle(999999, ComplianceEventKind::Suspended, 'x');

    expect($written)->toBeNull();
});

test('an appeal is kept as an excerpt, not as the whole text', function (): void {
    $business = Business::factory()->create();

    $event = app(RecordComplianceEvent::class)->handle($business->id, ComplianceEventKind::Appealed, str_repeat('palabra ', 100));

    expect(mb_strlen($event->detail))->toBeLessThanOrEqual(201);
});

test('the standing is today: a suspension that was lifted does not make it suspended', function (): void {
    $business = Business::factory()->create();
    app(RecordComplianceEvent::class)->handle($business->id, ComplianceEventKind::Suspended, 'sexual', null, now()->subDays(200));
    app(RecordComplianceEvent::class)->handle($business->id, ComplianceEventKind::SuspensionLifted, 'sexual', $this->admin->id, now()->subDays(199));

    $row = ComplianceEvent::board()->firstWhere('id', $business->id);

    // Two events, both older than the window and nothing open: it has a history and is in order.
    expect($row['standing'])->toBe(ComplianceEvent::CLEAR)
        ->and($row['total'])->toBe(2)
        ->and($row['recent'])->toBe(0);

    $business->forceFill(['suspended_at' => now(), 'suspension_reason' => 'sexual'])->save();

    expect(ComplianceEvent::board()->firstWhere('id', $business->id)['standing'])->toBe(ComplianceEvent::SUSPENDED);
});

test('the board puts the worst standing first and filters by it', function (): void {
    $suspended = Business::factory()->create(['name' => 'Suspendida SA']);
    $suspended->forceFill(['suspended_at' => now()])->save();
    $observed = Business::factory()->create(['name' => 'Observada SA']);
    $quiet = Business::factory()->create(['name' => 'Sin historial SA']);

    ComplianceEvent::factory()->create(['business_id' => $observed->id, 'occurred_at' => now()]);
    ComplianceEvent::factory()->create(['business_id' => $suspended->id, 'occurred_at' => now()->subDay()]);

    expect(ComplianceEvent::board()->pluck('name')->all())->toBe(['Suspendida SA', 'Observada SA'])
        ->and(ComplianceEvent::board(ComplianceEvent::SUSPENDED)->pluck('name')->all())->toBe(['Suspendida SA'])
        ->and(ComplianceEvent::boardCount('recent'))->toBe(2)
        ->and(ComplianceEvent::board('', 'observada')->pluck('name')->all())->toBe(['Observada SA'])
        ->and(ComplianceEvent::board()->pluck('id')->all())->not->toContain($quiet->id);
});

test('the backfill writes what the platform already knew, once', function (): void {
    $business = Business::factory()->create();
    ModerationFlag::query()->create(['business_id' => $business->id, 'source' => 'logo', 'kind' => 'image', 'severity' => ModerationSeverity::Rejected, 'category' => 'sexual', 'score' => 0.9, 'fingerprint' => 'abc']);
    $business->forceFill(['suspended_at' => now()->subDay(), 'suspension_reason' => 'sexual', 'appealed_at' => now(), 'appeal_message' => 'Error'])->save();

    $first = app(BackfillComplianceEvents::class)->handle();
    $second = app(BackfillComplianceEvents::class)->handle();

    expect($first)->toBe(3)
        ->and($second)->toBe(0)
        ->and(kindsOf($business))->toContain('content_rejected', 'suspended', 'appealed');
});

test('moderation codes read in words and what somebody wrote is shown as they wrote it', function (): void {
    $business = Business::factory()->create();

    $catch = ComplianceEvent::factory()->of(ComplianceEventKind::ContentSevere, 'logo · sexual/minors')->create(['business_id' => $business->id]);
    $appeal = ComplianceEvent::factory()->of(ComplianceEventKind::Appealed, 'logo · sexual/minors')->create(['business_id' => $business->id]);
    $unknown = ComplianceEvent::factory()->of(ComplianceEventKind::Suspended, 'una-categoria-nueva')->create(['business_id' => $business->id]);

    expect($catch->readableDetail())->toBe('Logo · Contenido sexual con menores')
        // Their own words are never "translated", even when they happen to look like a code.
        ->and($appeal->readableDetail())->toBe('logo · sexual/minors')
        // A code the file does not know yet still shows: nothing is hidden by a missing word.
        ->and($unknown->readableDetail())->toBe('una-categoria-nueva');
});

test('the business sheet links to its history, with how many lines it holds, for who may read it', function (): void {
    $business = Business::factory()->create(['name' => 'Peluquería Lola']);
    ComplianceEvent::factory()->count(2)->create(['business_id' => $business->id]);

    $this->actingAs($this->admin);

    Livewire::test('admin.businesses.index')
        ->set('open', $business->id)
        ->assertSee('Ver historial (2)')
        ->assertSeeHtml(route('admin.compliance', ['negocio' => $business->id]));

    // Support reads businesses but not their compliance history: no link, not a link that 403s.
    $support = User::factory()->create(['email_verified_at' => now()]);
    $support->assignRole('support');
    $this->actingAs($support);

    Livewire::test('admin.businesses.index')
        ->set('open', $business->id)
        ->assertDontSee('Ver historial');
});

test('the screen opens for staff with the permission and closes for a client', function (): void {
    $client = User::factory()->create(['email_verified_at' => now()]);
    $client->syncRoles(['client']);

    $this->actingAs($client)->get(route('admin.compliance'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.compliance'))->assertOk()->assertSee(__('compliance.title'));
});

test('with no files the screen says so, and an open file shows the fault and its answer', function (): void {
    $this->actingAs($this->admin);

    Livewire::test('admin.compliance.index')->assertSee(__('compliance.empty_title'));

    $business = Business::factory()->create(['name' => 'Peluquería Lola']);
    ComplianceEvent::factory()->of(ComplianceEventKind::Suspended, 'sexual')->create(['business_id' => $business->id, 'occurred_at' => now()->subDays(3)]);
    ComplianceEvent::factory()->of(ComplianceEventKind::SuspensionLifted, 'sexual')->create(['business_id' => $business->id, 'actor_id' => $this->admin->id, 'occurred_at' => now()->subDay()]);

    Livewire::test('admin.compliance.index')
        ->assertSee('Peluquería Lola')
        ->set('open', $business->id)
        ->assertSee(__('compliance.kinds.suspended'))
        ->assertSee(__('compliance.kinds.suspension_lifted'))
        ->assertSee($this->admin->name);
});
