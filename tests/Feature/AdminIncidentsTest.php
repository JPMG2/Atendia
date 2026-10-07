<?php

declare(strict_types=1);

use App\Classes\Main\Incidents;
use App\Enums\ConversationStatus;
use App\Enums\IncidentKind;
use App\Enums\MessageAuthor;
use App\Enums\MessageDirection;
use App\Enums\QuestionResolution;
use App\Mail\IncidentsDigest;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationAnalysis;
use App\Models\ConversationMessage;
use App\Models\ConversationQuestion;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/** A thread whose last word is the customer's, older than the window. */
function unansweredThread(Business $business, int $minutesAgo = 60): Conversation
{
    $thread = Conversation::factory()->create([
        'business_id' => $business->id,
        'last_message_at' => now()->subMinutes($minutesAgo),
    ]);

    ConversationMessage::factory()->create([
        'business_id' => $business->id,
        'conversation_id' => $thread->id,
        'direction' => MessageDirection::In,
        'body' => '¿Están abiertos?',
        'created_at' => now()->subMinutes($minutesAgo),
    ]);

    return $thread;
}

/** An admin who may open the desk, with the menu the layout reads. */
function deskAdmin(): User
{
    test()->seed(RolesAndPermissionsSeeder::class);
    test()->seed(MenuSeeder::class);

    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    return $admin;
}

test('a thread the assistant never answered is the worst thing on the desk', function (): void {
    $business = Business::factory()->create();
    unansweredThread($business);

    $desk = Incidents::now();

    expect($desk->countOf(IncidentKind::Unanswered))->toBe(1)
        ->and($desk->all->first()->kind)->toBe(IncidentKind::Unanswered)
        ->and($desk->all->first()->business)->toBe($business->name)
        ->and($desk->all->first()->excerpt)->toBe('¿Están abiertos?');
});

test('a thread the assistant answered is not an incident', function (): void {
    $business = Business::factory()->create();
    $thread = unansweredThread($business);

    ConversationMessage::factory()->create([
        'business_id' => $business->id,
        'conversation_id' => $thread->id,
        'direction' => MessageDirection::Out,
        'created_at' => now()->subMinutes(59),
    ]);

    expect(Incidents::now()->countOf(IncidentKind::Unanswered))->toBe(0);
});

test('a reply still in flight is not called a failure', function (): void {
    $business = Business::factory()->create();
    unansweredThread($business, minutesAgo: 2);

    expect(Incidents::now()->countOf(IncidentKind::Unanswered))->toBe(0);
});

test('silence is the design once a thread is with the team, so it is not unanswered', function (): void {
    $business = Business::factory()->create();
    $thread = unansweredThread($business);
    $thread->update(['status' => ConversationStatus::Team, 'escalated_at' => now()->subMinutes(5)]);

    expect(Incidents::now()->countOf(IncidentKind::Unanswered))->toBe(0);
});

test('a handoff nobody took is an incident of its own', function (): void {
    $business = Business::factory()->create();
    $thread = unansweredThread($business);
    $thread->update(['status' => ConversationStatus::Team, 'escalated_at' => now()->subHours(3)]);

    expect(Incidents::now()->countOf(IncidentKind::HandoffUnattended))->toBe(1);
});

test('a handoff a person replied to is not on the desk', function (): void {
    $business = Business::factory()->create();
    $thread = unansweredThread($business);
    $thread->update(['status' => ConversationStatus::Team, 'escalated_at' => now()->subHours(3)]);

    ConversationMessage::factory()->create([
        'business_id' => $business->id,
        'conversation_id' => $thread->id,
        'direction' => MessageDirection::Out,
        'author' => MessageAuthor::Human,
        'created_at' => now()->subHours(2),
    ]);

    expect(Incidents::now()->countOf(IncidentKind::HandoffUnattended))->toBe(0);
});

test('the assistant answering a handoff does not count as a person showing up', function (): void {
    $business = Business::factory()->create();
    $thread = unansweredThread($business);
    $thread->update(['status' => ConversationStatus::Team, 'escalated_at' => now()->subHours(3)]);

    ConversationMessage::factory()->create([
        'business_id' => $business->id,
        'conversation_id' => $thread->id,
        'direction' => MessageDirection::Out,
        'author' => MessageAuthor::Assistant,
        'created_at' => now()->subHours(2),
    ]);

    expect(Incidents::now()->countOf(IncidentKind::HandoffUnattended))->toBe(1);
});

test('a customer read as annoyed lands on the desk, a neutral one does not', function (): void {
    $business = Business::factory()->create();
    ConversationAnalysis::factory()->negative()->create(['business_id' => $business->id]);
    ConversationAnalysis::factory()->create(['business_id' => $business->id]);

    expect(Incidents::now()->countOf(IncidentKind::CustomerUpset))->toBe(1);
});

test('one unhappy customer read three times is one row, not three', function (): void {
    $business = Business::factory()->create();
    $thread = Conversation::factory()->create(['business_id' => $business->id]);

    ConversationAnalysis::factory()->negative()->count(3)->create([
        'business_id' => $business->id,
        'conversation_id' => $thread->id,
    ]);

    expect(Incidents::now()->countOf(IncidentKind::CustomerUpset))->toBe(1);
});

test('a demo business never fills the desk', function (): void {
    $demo = Business::factory()->create(['is_demo' => true]);
    unansweredThread($demo);
    ConversationAnalysis::factory()->negative()->create(['business_id' => $demo->id]);

    expect(Incidents::now()->isEmpty)->toBeTrue();
});

test('a failed job is on the desk with the first line of its exception', function (): void {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) str()->uuid(),
        'connection' => 'redis',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => "RuntimeException: el puente no respondió\n#0 /var/www/html/app/Jobs/Thing.php(12)",
        'failed_at' => now()->subMinutes(10),
    ]);

    $desk = Incidents::now();

    expect($desk->countOf(IncidentKind::JobFailed))->toBe(1)
        ->and($desk->all->first()->excerpt)->toBe('RuntimeException: el puente no respondió')
        ->and($desk->all->first()->business)->toBeNull();
});

test('one thread with two problems is one row, under its worst one', function (): void {
    // Found by LOOKING at the screenshot, not by a test: the same customer
    // sat in two tabs, and the header promised more work than there was.
    $business = Business::factory()->create();
    $thread = unansweredThread($business, minutesAgo: 480);

    ConversationAnalysis::factory()->negative()->create([
        'business_id' => $business->id,
        'conversation_id' => $thread->id,
    ]);

    $desk = Incidents::now();

    expect($desk->all)->toHaveCount(1)
        ->and($desk->countOf(IncidentKind::Unanswered))->toBe(1)
        ->and($desk->countOf(IncidentKind::CustomerUpset))->toBe(0);
});

test('a business behind most of one problem is named, and one accident is not', function (): void {
    $repeating = Business::factory()->create(['name' => 'Laboratorio Vida']);
    $other = Business::factory()->create();

    unansweredThread($repeating);
    unansweredThread($repeating);
    unansweredThread($other);

    $desk = Incidents::now();
    $concentrated = $desk->concentration(IncidentKind::Unanswered);

    expect($concentrated)->not->toBeNull()
        ->and($concentrated['business'])->toBe('Laboratorio Vida')
        ->and($concentrated['count'])->toBe(2)
        ->and($concentrated['total'])->toBe(3)
        // Three businesses with one each is three accidents, not a pattern.
        ->and($desk->concentration(IncidentKind::HandoffUnattended))->toBeNull();
});

test('one business with one problem is never called a pattern', function (): void {
    $business = Business::factory()->create();
    unansweredThread($business);

    expect(Incidents::now()->concentration(IncidentKind::Unanswered))->toBeNull();
});

test('a customer who asked twice with nobody answering is on the desk once', function (): void {
    $business = Business::factory()->create();
    $thread = Conversation::factory()->create(['business_id' => $business->id, 'contact_name' => 'Rubén']);
    $reading = ConversationAnalysis::factory()->create([
        'business_id' => $business->id,
        'conversation_id' => $thread->id,
    ]);

    foreach (['¿Hacen envíos?', '¿Pero hacen envíos o no?'] as $text) {
        ConversationQuestion::query()->create([
            'business_id' => $business->id,
            'conversation_id' => $thread->id,
            'conversation_analysis_id' => $reading->id,
            'question' => $text,
            'resolved_by' => QuestionResolution::Nobody,
        ]);
    }

    $desk = Incidents::now();

    expect($desk->countOf(IncidentKind::CustomerRepeated))->toBe(1)
        // The LAST one: the words they used when they were already tired.
        ->and($desk->all->first()->excerpt)->toBe('¿Pero hacen envíos o no?')
        ->and($desk->all->first()->customer)->toBe('Rubén');
});

test('asking once, or asking twice and being answered, is not insisting', function (): void {
    $business = Business::factory()->create();
    $once = Conversation::factory()->create(['business_id' => $business->id]);
    $answered = Conversation::factory()->create(['business_id' => $business->id]);
    $firstReading = ConversationAnalysis::factory()->create(['business_id' => $business->id, 'conversation_id' => $once->id]);
    $secondReading = ConversationAnalysis::factory()->create(['business_id' => $business->id, 'conversation_id' => $answered->id]);

    ConversationQuestion::query()->create([
        'business_id' => $business->id, 'conversation_id' => $once->id,
        'conversation_analysis_id' => $firstReading->id,
        'question' => 'Una sola vez', 'resolved_by' => QuestionResolution::Nobody,
    ]);

    foreach (['¿Hacen envíos?', '¿Y a Palermo?'] as $text) {
        ConversationQuestion::query()->create([
            'business_id' => $business->id, 'conversation_id' => $answered->id,
            'conversation_analysis_id' => $secondReading->id,
            'question' => $text, 'resolved_by' => QuestionResolution::Assistant,
        ]);
    }

    expect(Incidents::now()->countOf(IncidentKind::CustomerRepeated))->toBe(0);
});

test('the desk is sorted by severity, never by arrival', function (): void {
    $business = Business::factory()->create();

    // The annoyed reading is the OLDEST, so arrival order would put it first.
    ConversationAnalysis::factory()->negative()->create([
        'business_id' => $business->id,
        'created_at' => now()->subHours(20),
    ]);
    unansweredThread($business, minutesAgo: 30);

    expect(Incidents::now()->all->pluck('kind')->map->value->all())
        ->toBe([IncidentKind::Unanswered->value, IncidentKind::CustomerUpset->value]);
});

test('the desk opens on the worst tab that has something', function (): void {
    $business = Business::factory()->create();
    ConversationAnalysis::factory()->negative()->create(['business_id' => $business->id]);

    $this->actingAs(deskAdmin())->get(route('admin.incidents'))
        ->assertOk()
        ->assertSee(__('incidents.kinds.customer_upset.label'))
        ->assertSee("tab = '".IncidentKind::CustomerUpset->value."'", false);
});

test('with nothing wrong the screen says so instead of drawing an empty table', function (): void {
    $this->actingAs(deskAdmin())->get(route('admin.incidents'))
        ->assertOk()
        ->assertSee(__('incidents.all_good_title'))
        // The heading of the evidence column: it exists only inside the table.
        ->assertDontSee(__('incidents.table.last'));
});

test('the day ends with a mail naming what went wrong', function (): void {
    Mail::fake();
    config()->set('atendia.admin_email', 'equipo@atendia.test');
    // The command finds her by ADMIN_EMAIL, not by role: no seeder needed.
    User::factory()->create(['email' => 'equipo@atendia.test']);

    $business = Business::factory()->create(['name' => 'Laboratorio Vida']);
    unansweredThread($business);

    $this->artisan('atendia:incidents-digest')->assertSuccessful();

    Mail::assertQueued(IncidentsDigest::class, function (IncidentsDigest $mail): bool {
        return count($mail->rows) === 1
            && str_contains($mail->lines()[0], 'La IA no contestó')
            && str_contains($mail->lines()[0], 'Laboratorio Vida');
    });
});

test('a clean day sends no mail at all, so the one that arrives is read', function (): void {
    Mail::fake();
    config()->set('atendia.admin_email', 'equipo@atendia.test');
    User::factory()->create(['email' => 'equipo@atendia.test']);

    $this->artisan('atendia:incidents-digest')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('with no admin account the digest says so instead of failing', function (): void {
    Mail::fake();
    config()->set('atendia.admin_email', 'nadie@atendia.test');
    unansweredThread(Business::factory()->create());

    $this->artisan('atendia:incidents-digest')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('a client cannot reach the incidents desk', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $client = User::factory()->create();

    $this->actingAs($client)->get(route('admin.incidents'))->assertForbidden();
});
