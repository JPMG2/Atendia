<?php

declare(strict_types=1);

use App\Actions\Account\CloseAccount;
use App\Actions\Admin\RaisePlatformEvent;
use App\Actions\Admin\ResolvePlatformEvent;
use App\Actions\Billing\ApprovePayment;
use App\Actions\Billing\CancelSubscription;
use App\Actions\Billing\RejectPayment;
use App\Actions\Billing\SubmitPaymentReceipt;
use App\Actions\Compliance\RecordComplianceEvent;
use App\Enums\ComplianceEventKind;
use App\Enums\PlatformEventKind;
use App\Enums\PlatformEventSeverity;
use App\Enums\PlatformEventState;
use App\Events\BusinessCreated;
use App\Listeners\NotifyPlatformOfNewBusiness;
use App\Models\Business;
use App\Models\PlatformEvent;
use App\Models\User;
use App\Models\WhatsAppLinkEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The platform's feed
|--------------------------------------------------------------------------
| What the admin has to know is written when it happens, grouped when it
| repeats, and closed when the condition goes away. These pin each of those.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    Mail::fake();
    Storage::fake('local');
});

/** @return list<string> the kinds still waiting, open or seen */
function activeKinds(): array
{
    return PlatformEvent::query()->active()->orderBy('id')->get()
        ->map(fn (PlatformEvent $event): string => $event->kind->value)->all();
}

test('a fact that repeats is one line that counts, not many lines', function (): void {
    $business = Business::factory()->create();
    PlatformEvent::query()->delete();

    $raise = app(RaisePlatformEvent::class);
    $raise->handle(PlatformEventKind::WhatsAppDisconnected, $business->id, 'bridge_closed');
    $raise->handle(PlatformEventKind::WhatsAppDisconnected, $business->id, 'bridge_closed');
    $raise->handle(PlatformEventKind::WhatsAppDisconnected, $business->id);

    $line = PlatformEvent::query()->sole();

    expect($line->occurrences)->toBe(3)
        ->and($line->detail)->toBe('bridge_closed')
        ->and($line->about_business_id)->toBe($business->id);
});

test('two businesses never share a line', function (): void {
    [$a, $b] = [Business::factory()->create(), Business::factory()->create()];
    PlatformEvent::query()->delete();

    app(RaisePlatformEvent::class)->handle(PlatformEventKind::WhatsAppDisconnected, $a->id);
    app(RaisePlatformEvent::class)->handle(PlatformEventKind::WhatsAppDisconnected, $b->id);

    expect(PlatformEvent::query()->count())->toBe(2);
});

test('once resolved, the same fact opens a new line', function (): void {
    $business = Business::factory()->create();
    PlatformEvent::query()->delete();

    app(RaisePlatformEvent::class)->handle(PlatformEventKind::WhatsAppDisconnected, $business->id);
    app(ResolvePlatformEvent::class)->ofKind(PlatformEventKind::WhatsAppDisconnected, $business->id);
    app(RaisePlatformEvent::class)->handle(PlatformEventKind::WhatsAppDisconnected, $business->id);

    expect(PlatformEvent::query()->count())->toBe(2)
        ->and(PlatformEvent::query()->where('state', PlatformEventState::Resolved->value)->count())->toBe(1)
        ->and(PlatformEvent::query()->active()->count())->toBe(1);
});

test('severity comes from the kind, never from the writer', function (): void {
    $business = Business::factory()->create();
    PlatformEvent::query()->delete();

    app(RaisePlatformEvent::class)->handle(PlatformEventKind::BusinessSuspended, $business->id);
    app(RaisePlatformEvent::class)->handle(PlatformEventKind::ReceiptReceived, $business->id, null, 'receipt_received:1');

    expect(PlatformEvent::query()->where('kind', 'business_suspended')->value('severity'))->toBe(PlatformEventSeverity::Critical)
        ->and(PlatformEvent::query()->where('kind', 'receipt_received')->value('severity'))->toBe(PlatformEventSeverity::Important)
        ->and(PlatformEventSeverity::Critical->interrupts())->toBeTrue()
        ->and(PlatformEventSeverity::Important->interrupts())->toBeFalse();
});

test('raising never breaks what it reports', function (): void {
    // A business that does not exist breaks the foreign key: the caller must not feel it.
    $line = app(RaisePlatformEvent::class)->handle(PlatformEventKind::BusinessSuspended, 987654321);

    expect($line)->toBeNull();
});

test('every penalty passes through the one door and reaches the feed', function (): void {
    $business = Business::factory()->create();
    PlatformEvent::query()->delete();
    $record = app(RecordComplianceEvent::class);

    $record->handle($business->id, ComplianceEventKind::Suspended, 'sexual');
    $record->handle($business->id, ComplianceEventKind::Appealed, 'Es lencería.');
    $record->handle($business->id, ComplianceEventKind::ContentRejected, 'logo · sexual');

    expect(activeKinds())->toBe(['business_suspended', 'appeal_received', 'content_to_review']);

    $record->handle($business->id, ComplianceEventKind::SuspensionLifted, 'sexual');

    // Lifting closes the suspension and the appeal; the file to review stays hers to decide.
    expect(activeKinds())->toBe(['content_to_review']);
});

test('a late payment is replaced by its pause, and regularizing closes both', function (): void {
    $business = Business::factory()->create();
    PlatformEvent::query()->delete();
    $record = app(RecordComplianceEvent::class);

    $record->handle($business->id, ComplianceEventKind::PaymentLate);
    expect(activeKinds())->toBe(['payment_late']);

    $record->handle($business->id, ComplianceEventKind::PaymentPaused);
    expect(activeKinds())->toBe(['payment_paused']);

    $record->handle($business->id, ComplianceEventKind::PaymentRegularized);
    expect(activeKinds())->toBe([]);
});

test('a refused receipt is her own decision and not news', function (): void {
    $business = Business::factory()->create();
    PlatformEvent::query()->delete();

    app(RecordComplianceEvent::class)->handle($business->id, ComplianceEventKind::PaymentRejected, 'No coincide');

    expect(PlatformEvent::query()->count())->toBe(0);
});

test('a new business reaches the feed even when there is nobody to mail', function (): void {
    config()->set('atendia.admin_email', 'nobody@example.test');
    $business = Business::factory()->create(['name' => 'Panadería Lumen']);
    PlatformEvent::query()->delete();

    (new NotifyPlatformOfNewBusiness)->handle(new BusinessCreated($business));

    $line = PlatformEvent::query()->sole();

    expect($line->kind)->toBe(PlatformEventKind::BusinessJoined)
        ->and($line->detail)->toBe('Panadería Lumen')
        ->and($line->about_business_id)->toBe($business->id);
});

test('a receipt waits on the feed until somebody decides it', function (): void {
    $admin = User::factory()->create();
    $business = Business::factory()->create();
    PlatformEvent::query()->delete();

    $first = app(SubmitPaymentReceipt::class)->handle($business, UploadedFile::fake()->image('r1.jpg'), 'REF-1');
    $second = app(SubmitPaymentReceipt::class)->handle($business, UploadedFile::fake()->image('r2.jpg'), 'REF-2');

    expect(activeKinds())->toBe(['receipt_received', 'receipt_received']);

    app(ApprovePayment::class)->handle($first, $admin);
    // The decided receipt closes; the credited payment opens its own line.
    expect(activeKinds())->toBe(['receipt_received', 'payment_approved']);

    app(RejectPayment::class)->handle($second, $admin, 'No coincide');
    expect(activeKinds())->toBe(['payment_approved']);
});

test('every credited payment is its own line, and a plan that moved adds one', function (): void {
    $admin = User::factory()->create();
    $business = Business::factory()->create();
    $business->subscription->forceFill(['plan' => 'emprende'])->save();
    PlatformEvent::query()->delete();

    $same = app(SubmitPaymentReceipt::class)->handle($business, UploadedFile::fake()->image('a.jpg'), 'REF-A');
    app(ApprovePayment::class)->handle($same, $admin);

    expect(activeKinds())->toBe(['payment_approved']);

    $upgrade = app(SubmitPaymentReceipt::class)->handle($business, UploadedFile::fake()->image('b.jpg'), 'REF-B');
    $upgrade->forceFill(['plan' => 'premium'])->save();
    app(ApprovePayment::class)->handle($upgrade, $admin);

    $change = PlatformEvent::query()->where('kind', PlatformEventKind::PlanChanged->value)->sole();

    expect(activeKinds())->toBe(['payment_approved', 'payment_approved', 'plan_changed'])
        ->and($change->detail)->toBe('emprende → premium')
        ->and($change->severity)->toBe(PlatformEventSeverity::Info);
});

test('asking to leave reaches the feed, and changing her mind takes it back', function (): void {
    $business = Business::factory()->create();
    PlatformEvent::query()->delete();

    app(CancelSubscription::class)->handle($business->subscription);

    $line = PlatformEvent::query()->sole();

    expect($line->kind)->toBe(PlatformEventKind::BusinessCancelled)
        ->and($line->severity)->toBe(PlatformEventSeverity::Important)
        ->and($line->about_business_id)->toBe($business->id);

    app(CancelSubscription::class)->undo($business->subscription->refresh());

    expect(activeKinds())->toBe([]);
});

test('closing the account is a departure on the feed', function (): void {
    $business = Business::factory()->create();
    $owner = User::factory()->create(['business_id' => $business->id]);
    PlatformEvent::query()->delete();

    app(CloseAccount::class)->handle($owner);

    $line = PlatformEvent::query()->sole();

    expect($line->kind)->toBe(PlatformEventKind::BusinessCancelled)
        ->and($line->about_business_id)->toBe($business->id);
});

test('the number going down and coming back opens and closes its line', function (): void {
    $business = Business::factory()->create();
    PlatformEvent::query()->delete();

    WhatsAppLinkEvent::record($business, false, 'bridge_closed');
    expect(activeKinds())->toBe(['whatsapp_disconnected']);

    WhatsAppLinkEvent::record($business, true, 'paired');
    expect(activeKinds())->toBe([]);
});

test('the board puts the worst first and hides what is resolved', function (): void {
    PlatformEvent::factory()->ofKind(PlatformEventKind::BusinessJoined)->create(['last_seen_at' => now()->subHour()]);
    PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentPaused)->create(['last_seen_at' => now()->subDay()]);
    PlatformEvent::factory()->ofKind(PlatformEventKind::ReceiptReceived)->create(['last_seen_at' => now()]);
    PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentLate)->resolved()->create();

    $kinds = PlatformEvent::board(10)->map(fn (PlatformEvent $e): string => $e->kind->value)->all();

    expect($kinds)->toBe(['payment_paused', 'receipt_received', 'business_joined'])
        ->and(PlatformEvent::board(10, withResolved: true))->toHaveCount(4)
        ->and(PlatformEvent::board(1))->toHaveCount(1)
        ->and(PlatformEvent::board(10, PlatformEventSeverity::Critical))->toHaveCount(1);
});

test('the counts only include what is still waiting', function (): void {
    PlatformEvent::factory()->ofKind(PlatformEventKind::BusinessSuspended)->create();
    PlatformEvent::factory()->ofKind(PlatformEventKind::ReceiptReceived)->create();
    PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentLate)->acknowledged()->create();
    PlatformEvent::factory()->ofKind(PlatformEventKind::PaymentLate)->resolved()->create();

    expect(PlatformEvent::counts())->toBe(['critical' => 1, 'important' => 2, 'info' => 0, 'unseen' => 2]);
});

test('acknowledging is done once and by the first person who saw it', function (): void {
    [$first, $second] = [User::factory()->create(), User::factory()->create()];
    $line = PlatformEvent::factory()->ofKind(PlatformEventKind::ReceiptReceived)->create();

    $line->acknowledge($first);
    $line->refresh()->acknowledge($second);

    expect($line->refresh()->state)->toBe(PlatformEventState::Acknowledged)
        ->and($line->acknowledged_by)->toBe($first->id);
});
