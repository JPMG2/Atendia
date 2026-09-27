<?php

declare(strict_types=1);

use App\Enums\MessageAuthor;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSuggestion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Conversations — the date filter, in a real browser
|--------------------------------------------------------------------------
| Flatpickr and the Alpine glue live in JS the PHP tests never execute:
| only a browser proves the calendar actually opens and commits.
*/

test('the date filter opens the house-dressed calendar and picking a day filters the list', function (): void {
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    $conversation = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'contact_name' => 'Carla',
        'contact_phone' => '5491111111111',
    ]);
    ConversationMessage::factory()->for($conversation)->create([
        'business_id' => $user->business_id, 'body' => 'Hola',
    ]);

    $this->actingAs($user);

    $page = visit('/conversaciones');

    $page->assertSee('Carla')
        ->click('#if-dates')
        ->assertVisible('.flatpickr-calendar.open')
        ->screenshotElement('.flatpickr-calendar.open', 'calendar-open')
        ->assertNoJavaScriptErrors();

    // One click = one day; clicking outside closes and commits. Today holds
    // the exchange, so the thread must survive the filter round-trip.
    $page->click('.flatpickr-calendar.open .flatpickr-day.today')
        ->assertNoJavaScriptErrors()
        ->assertValue('#if-dates', now()->format('d/m/Y'))
        ->click('.page-head-title')
        ->assertSee('Carla')
        // Livewire re-rendered on commit: wire:ignore must have kept the
        // date flatpickr painted, or the field looks empty while filtering.
        ->assertValue('#if-dates', now()->format('d/m/Y'))
        ->assertNoJavaScriptErrors();
});

test('the customer sheet slides over the inbox with the fiche fields', function (): void {
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    $customer = Customer::factory()->create([
        'business_id' => $user->business_id,
        'phone' => '5491111111111',
        'profile_name' => 'Carli',
    ]);
    $thread = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'contact_name' => 'Carla',
        'contact_phone' => '5491111111111',
        'customer_id' => $customer->id,
    ]);
    ConversationMessage::factory()->for($thread)->create([
        'business_id' => $user->business_id, 'body' => 'Hola',
    ]);

    $this->actingAs($user);

    $page = visit('/conversaciones');

    $page->click('Carla')
        ->click(__('client.customers.open'))
        ->assertSee(__('client.customers.field_birthday'))
        ->assertSee(__('client.customers.opt_in_button'))
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'conversations-customer-sheet');
});

test('walking back in time flows through the thread island', function (): void {
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    $thread = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'contact_name' => 'Carla',
        'contact_phone' => '5491111111111',
    ]);

    foreach (range(1, 35) as $i) {
        ConversationMessage::factory()->for($thread)->create([
            'business_id' => $user->business_id,
            'body' => "Mensaje {$i}",
        ]);
    }

    $this->actingAs($user);

    // The island round-trip only exists in a real browser: the PHP tests
    // exercise loadOlder through a full render, never the scoped request.
    $page = visit('/conversaciones');

    $page->click('Carla')
        ->assertSee('Mensaje 35')
        ->click(__('client.conversations.older'))
        ->assertSee('Mensaje 1')
        ->assertNoJavaScriptErrors();
});

test('the provenance trail unfolds and the teach sheet opens from inside the island', function (): void {
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    $thread = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'contact_name' => 'Carla',
        'contact_phone' => '5491111111111',
    ]);
    $asked = ConversationMessage::factory()->for($thread)->create([
        'business_id' => $user->business_id, 'body' => '¿Tienen alternadores?',
    ]);
    ConversationMessage::factory()->out()->for($thread)->create([
        'business_id' => $user->business_id,
        'author' => MessageAuthor::Assistant,
        'body' => 'Sí, tenemos stock.',
        'knowledge_sources' => [['id' => 7, 'title' => 'Inventario 2026']],
    ]);

    // The teach door opens where the analysis queued the question.
    $suggestion = KnowledgeSuggestion::factory()->askedIn($thread)->create(['business_id' => $user->business_id, 'question' => '¿Tienen alternadores?']);
    $suggestion->questions()->update(['conversation_message_id' => $asked->id]);

    $this->actingAs($user);

    $page = visit('/conversaciones');

    // The Alpine toggle only lives in a real browser: closed by default,
    // the source titles appear on click.
    $page->click('Carla')
        ->assertSee(__('client.conversations.sources_toggle'))
        ->assertDontSee('Inventario 2026')
        ->click(__('client.conversations.sources_toggle'))
        ->assertSee('Inventario 2026')
        ->screenshotElement('.pm-bubble.out', 'sources-open')
        ->assertNoJavaScriptErrors();

    // "Teach" fires from island context; the sheet must render anyway —
    // it lives inside the island precisely for this scoped request.
    $page->click('[aria-label="'.__('client.conversations.teach').'"]')
        ->assertSee(__('client.assistant.sheet_new'))
        ->assertValue('#if-question', '¿Tienen alternadores?')
        ->assertNoJavaScriptErrors();
});

test('a taught source opens its edit sheet and the thread wears the birth badge', function (): void {
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    $thread = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'contact_name' => 'Carla',
        'contact_phone' => '5491111111111',
    ]);
    // The bubble's wording differs from the FAQ title on purpose: the click
    // by text below must land on the source button, never on the bubble.
    ConversationMessage::factory()->for($thread)->create([
        'business_id' => $user->business_id, 'body' => '¿Ustedes hacen envíos al interior?',
    ]);

    // Born in this very thread: the header must wear the badge. Without
    // events so the indexing job never fires a real embedding call.
    $faq = KnowledgeDocument::withoutEvents(fn () => KnowledgeDocument::factory()->create([
        'business_id' => $user->business_id,
        'source_type' => 'faq',
        'conversation_id' => $thread->id,
        'title' => '¿Hacen envíos?',
        'content' => "Pregunta: ¿Hacen envíos?\nRespuesta: Sí, a todo el país.",
    ]));

    ConversationMessage::factory()->out()->for($thread)->create([
        'business_id' => $user->business_id,
        'author' => MessageAuthor::Assistant,
        'body' => 'Sí, hacemos envíos a todo el país.',
        'knowledge_sources' => [['id' => $faq->id, 'title' => $faq->title]],
    ]);

    KnowledgeSuggestion::factory()->askedIn($thread)->create([
        'business_id' => $user->business_id,
        'question' => '¿Hacen envíos con cadena de frío?',
    ]);

    $this->actingAs($user);

    // The unanswered queue offers the context door; walking through it must
    // land on Conversaciones with THIS thread already open (the ?hilo link).
    $assistant = visit('/asistente');

    // No JS-error assertions in this long test: with no Reverb behind the
    // test server, pusher-js's script fallback 404s into a SyntaxError
    // given enough time. The short tests above keep that guard.
    $assistant->assertSee(__('client.assistant.view_thread'))
        ->assertSee(trans_choice('client.assistant.times_used', 1, ['count' => 1]))
        ->screenshotElement('.card.mb-4', 'misses-thread-link')
        ->screenshotElement('.card.mt-4', 'faq-usage-tally')
        ->click(__('client.assistant.view_thread'))
        ->assertQueryStringHas('hilo', (string) $thread->id)
        ->assertSee('¿Ustedes hacen envíos al interior?');

    $page = visit('/conversaciones');

    // The badge is a door too: it unfolds the answers born here and each
    // one opens its sheet — same jump the trail offers.
    $page->click('Carla')
        ->assertSee(trans_choice('client.conversations.taught_badge', 1, ['count' => 1]))
        ->screenshot(fullPage: true)
        ->click(trans_choice('client.conversations.taught_badge', 1, ['count' => 1]))
        ->assertVisible('.taught-popover')
        ->screenshotElement('.taught-popover', 'taught-popover')
        ->click('.taught-popover li button')
        ->assertSee(__('client.assistant.sheet_edit'))
        ->assertValue('#if-question', '¿Hacen envíos?')
        ->click(__('client.assistant.sheet_cancel'));

    $page->click(__('client.conversations.sources_toggle'))
        ->screenshotElement('.pm-bubble.out', 'trail-clickable-source')
        // By title, not text: the popover holds the same words, hidden.
        ->click('[title="'.__('client.conversations.source_open').'"]')
        ->assertSee(__('client.assistant.sheet_edit'))
        ->assertValue('#if-question', '¿Hacen envíos?')
        ->screenshotElement('.slide-over', 'source-edit-sheet');
});

test('a customer photo, document and location read as previews inside the bubble', function (): void {
    $user = User::factory()->create();
    $user->business()->associate(Business::factory()->create())->save();

    $thread = Conversation::factory()->create([
        'business_id' => $user->business_id,
        'contact_name' => 'Carla',
        'contact_phone' => '5491111111111',
    ]);

    // Real JPEGs, served by the private route: the previews must actually paint.
    $photos = collect([[14, 164, 122], [255, 106, 77], [60, 90, 200]])->map(function (array $rgb, int $at) use ($user, $thread): string {
        $image = imagecreatetruecolor(320, 240);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        ob_start();
        imagejpeg($image);
        $path = "businesses/{$user->business_id}/conversations/{$thread->id}/browser-photo-{$at}.jpg";
        Storage::disk('local')->put($path, (string) ob_get_clean());

        return $path;
    });

    ConversationMessage::factory()->for($thread)->create([
        'business_id' => $user->business_id,
        'body' => "📷 Foto\n¿Tienen este repuesto?\n📄 Documento: lista.pdf\n📍 Ubicación: Av. Siempreviva 742",
        'media' => [
            ['kind' => 'image', 'mime' => 'image/jpeg', 'path' => $photos[0], 'caption' => '¿Tienen este repuesto?'],
            ['kind' => 'document', 'mime' => 'application/pdf', 'name' => 'lista.pdf'],
            ['kind' => 'location', 'lat' => -34.6, 'lng' => -58.4, 'label' => 'Av. Siempreviva 742'],
        ],
        'media_text' => 'Lista de materiales: 3 bolsas de cemento Loma Negra y 2 m3 de arena',
    ]);
    ConversationMessage::factory()->for($thread)->create([
        'business_id' => $user->business_id,
        'body' => "📷 Foto\n📷 Foto\nEn rojo o en azul",
        'media' => [
            ['kind' => 'image', 'mime' => 'image/jpeg', 'path' => $photos[1]],
            ['kind' => 'image', 'mime' => 'image/jpeg', 'path' => $photos[2], 'caption' => 'En rojo o en azul'],
        ],
    ]);

    $this->actingAs($user);

    $page = visit('/conversaciones');

    $page->click('Carla')
        ->assertVisible('.pm-media img >> nth=0')
        ->assertSee('¿Tienen este repuesto?')
        ->assertSee('lista.pdf')
        ->assertSee(__('assistant.media.on_phone'))
        ->assertSee(__('assistant.media.open_map'))
        ->assertScript('document.querySelector(".pm-bubble.in").innerText.includes("📷 Foto")', false)
        ->assertScript('document.querySelector(".pm-media img").naturalWidth', 320)
        ->assertCount('.pm-place-tiles img', 9)
        ->assertAttributeContains('.pm-place-tiles img >> nth=4', 'src', 'tile.openstreetmap.org/16/')
        ->wait(2)
        ->screenshotElement('.pm-bubble.in >> nth=0', 'conversation-media-bubble')
        ->assertNoJavaScriptErrors();

    // The browser API has no double click: fire one at the photo's center.
    $doubleClickCurrentPhoto = <<<'JS'
        const photo = document.querySelector('.photo-viewer-photo[data-current=true]');
        const box = photo.getBoundingClientRect();
        photo.dispatchEvent(new MouseEvent('dblclick', { bubbles: true, clientX: box.left + box.width / 2, clientY: box.top + box.height / 2 }));
        JS;

    // Any photo opens the carousel of the whole thread, in order.
    $page->click('.pm-media >> nth=1')
        ->assertVisible('.photo-viewer')
        ->assertSee('2 de 3')
        ->assertCount('.photo-viewer-thumb', 3)
        ->screenshot(filename: 'conversation-media-viewer')
        ->keys('.photo-viewer', 'ArrowRight')
        ->assertSee('3 de 3')
        ->assertSee('En rojo o en azul')
        ->assertScript('document.querySelector(".photo-viewer-nav.next").disabled', true)
        ->click('.photo-viewer-thumb >> nth=0')
        ->assertSee('1 de 3')
        ->assertSee('¿Tienen este repuesto?')
        // Double click zooms at the cursor; the arrows step aside while zoomed.
        ->script($doubleClickCurrentPhoto);

    $page->assertSee('250%')
        ->assertScript('document.querySelector(".photo-viewer-nav.next")._x_isShown', false)
        ->screenshot(filename: 'conversation-media-viewer-zoomed')
        ->script($doubleClickCurrentPhoto);

    $page->assertSee('100%')
        ->keys('.photo-viewer', 'Escape')
        ->assertScript('document.querySelector(".photo-viewer")._x_isShown', false)
        ->assertNoJavaScriptErrors();

    // Phone width: arrows give way to the swipe, the strip still fits.
    $page->resize(390, 844)
        ->screenshot(filename: 'conversation-thread-mobile')
        ->click('.pm-media >> nth=2')
        ->assertSee('3 de 3')
        ->screenshot(filename: 'conversation-media-viewer-mobile')
        ->keys('.photo-viewer', 'Escape');

    // "Archivos": the whole thread's attachments in one sheet; a photo there opens the carousel too.
    $page->resize(1280, 900)
        ->click(__('client.conversations.files.open'))
        ->assertSee(__('client.conversations.files.title'))
        ->assertCount('.pm-files-photo', 3)
        ->screenshot(filename: 'conversation-files-photos')
        ->click('[role="tab"] >> nth=1')
        ->assertSee('lista.pdf')
        ->screenshot(filename: 'conversation-files-documents')
        ->click('[role="tab"] >> nth=0')
        ->click('.pm-files-photo >> nth=0')
        ->assertSee('1 de 3')
        ->keys('.photo-viewer', 'Escape')
        ->keys('.slide-over', 'Escape')
        ->assertNoJavaScriptErrors();

    // Dark theme: the place card and the photo keep their contrast.
    $page->resize(1280, 900)
        ->script('document.documentElement.classList.add("dark"); document.documentElement.dataset.theme = "dark"');
    $page->screenshotElement('.pm-bubble.in >> nth=0', 'conversation-media-bubble-dark');

    // The inbox row counts the thread's files; the thread search reaches inside the PDF.
    $page->script('document.documentElement.classList.remove("dark"); delete document.documentElement.dataset.theme');
    $page->assertVisible('li[wire\\:key^="row-"] .pm-files-count')
        ->screenshotElement('li[wire\\:key^="row-"] >> nth=0', 'conversation-list-row-files')
        ->fill('thread_search', 'cemento')
        ->wait(1)
        ->assertSee(__('client.conversations.files.found_in_pdf'))
        ->screenshotElement('.pm-bubble.in >> nth=0', 'conversation-pdf-search-hit')
        ->assertNoJavaScriptErrors();

    $photos->each(fn (string $path): bool => Storage::disk('local')->delete($path));
});
