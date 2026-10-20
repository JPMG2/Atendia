<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\HelpArticle;
use App\Models\Menu;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Help\HelpFinder;
use App\Services\Knowledge\KnowledgeEmbedder;
use App\Services\Search\GlobalSearch;
use Database\Seeders\HelpArticleSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SupportTicketKindSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Help before the ticket
|--------------------------------------------------------------------------
| Gartner: only 14% of problems are fully solved in self-service, and a bad
| channel switch burns the next attempt. So the help answers first and the
| report is never behind a wall — those two facts are what these tests pin.
*/

beforeEach(function (): void {
    app()->setLocale('es');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(HelpArticleSeeder::class);
    $this->seed(SupportTicketKindSeeder::class);

    $this->business = Business::factory()->create();
    $this->user = User::factory()->create();
    $this->user->business()->associate($this->business)->save();
});

test('the menu item finally leads somewhere', function (): void {
    $this->seed(MenuSeeder::class);
    $this->user->assignRole('client');

    $this->actingAs($this->user->refresh())->get('/ayuda')->assertOk();

    expect(Menu::query()->where('label_key', 'menu.help')->value('route_name'))->toBe('help');
});

test('the report is offered from the first second, with nothing typed', function (): void {
    Livewire::actingAs($this->user)
        ->test('help.index')
        ->assertSeeHtml('data-testid="help-report"');
});

test('the answers of the screen she came from lead the list', function (): void {
    // The heading names the screen the way the menu does.
    $this->seed(MenuSeeder::class);

    Livewire::actingAs($this->user)
        ->test('help.index', ['from' => 'whatsapp'])
        ->assertSee(__('help.for_screen', ['screen' => 'WhatsApp']))
        ->assertSee('Cómo conecto mi WhatsApp');
});

test('it is searched by what happens to her, not by what we call it', function (): void {
    // She types the symptom; the article is titled as a task.
    $hits = HelpArticle::suggest(null, 'no responde');

    expect($hits->pluck('slug'))->toContain('whatsapp-se-desconecto');

    // And the accents are not hers to remember.
    expect(HelpArticle::suggest(null, 'telefono')->pluck('slug'))->toContain('conectar-whatsapp');
});

test('punctuation glued to a word does not hide the article', function (): void {
    $hits = HelpArticle::suggest('whatsapp', 'en vez de conectado me sale sin verificar, que significa eso?');

    expect($hits->pluck('slug'))->toContain('whatsapp-sin-verificar');
});

test('the article matching more words of the sentence beats the ones matching a generic word', function (): void {
    $hits = HelpArticle::suggest(null, 'me voy de vacaciones, como apago el asistente?', 3);

    expect($hits->first()->slug)->toBe('apagar-asistente');
});

test('a thumb down counts and hands her over with the article named', function (): void {
    $article = HelpArticle::query()->where('slug', 'conectar-whatsapp')->sole();

    Livewire::actingAs($this->user)
        ->test('help.index')
        ->call('rate', $article->id, false)
        ->assertDispatched('support-open', screen: 'whatsapp', about: $article->title);

    expect($article->fresh()->unhelpful_count)->toBe(1)
        ->and($article->fresh()->helpful_count)->toBe(0);
});

test('the same person cannot vote the same article twice', function (): void {
    $article = HelpArticle::query()->where('slug', 'conectar-whatsapp')->sole();

    Livewire::actingAs($this->user)
        ->test('help.index')
        ->call('rate', $article->id, true)
        ->call('rate', $article->id, true);

    expect($article->fresh()->helpful_count)->toBe(1);
});

test('finding nothing is not a dead end', function (): void {
    Livewire::actingAs($this->user)
        ->test('help.index')
        ->set('search', 'xyzzy')
        ->assertSee(__('help.empty_title', ['term' => 'xyzzy']))
        // The way out is right there: a dead end is worse than no help at all.
        ->assertSee(__('help.report'));
});

test('the report suggests up to three answers while she writes, and never blocks', function (): void {
    $widget = Livewire::actingAs($this->user)
        ->test('support.widget', ['from' => 'whatsapp'])
        ->call('open')
        ->set('form.body', 'mi asistente dejo de responder desde ayer');

    $widget->assertSeeHtml('data-testid="support-suggestions"')
        ->assertSee('Mi asistente dejó de responder')
        // Offered, not imposed: Send is still right there.
        ->assertSee(__('support.send'));

    expect($widget->instance()->suggestions)->toHaveCount(HelpArticle::SUGGESTIONS);
});

test('a short beginning suggests nothing, because there is nothing to match yet', function (): void {
    $widget = Livewire::actingAs($this->user)
        ->test('support.widget')
        ->call('open')
        ->set('form.body', 'no anda');

    expect($widget->instance()->suggestions)->toBeEmpty();
});

test('handed over from an article, the form arrives pointing at its screen', function (): void {
    Livewire::actingAs($this->user)
        ->test('support.widget')
        ->call('open', 'whatsapp', 'Cómo conecto mi WhatsApp')
        ->assertSet('form.screen', 'whatsapp')
        ->assertSet('form.body', fn (string $body): bool => str_contains($body, 'Cómo conecto mi WhatsApp'));
});

test('meaning is only paid for when the words found nothing', function (): void {
    $embedder = mock(KnowledgeEmbedder::class);
    // "no responde" matches by words, so the expensive lane must stay shut.
    $embedder->shouldReceive('embedOne')->never();
    app()->instance(KnowledgeEmbedder::class, $embedder);

    expect(app(HelpFinder::class)->find(null, 'mi asistente no responde desde ayer'))->not->toBeEmpty();
});

test('when her words are not ours, meaning answers', function (): void {
    $vector = array_values(array_replace(array_fill(0, 1536, 0.0), [0 => 1.0]));

    $article = HelpArticle::query()->where('slug', 'conectar-whatsapp')->sole();
    $article->forceFill(['embedding' => $vector])->saveQuietly();

    $embedder = mock(KnowledgeEmbedder::class);
    $embedder->shouldReceive('embedOne')->once()->andReturn($vector);
    app()->instance(KnowledgeEmbedder::class, $embedder);

    expect(HelpArticle::query()->whereNotNull('embedding')->count())->toBe(1);
    expect(HelpArticle::closestInMeaning($vector)->pluck('slug'))->toContain('conectar-whatsapp');

    // No word of this is in any title or keyword: only meaning can find it.
    $hits = app(HelpFinder::class)->find(null, 'apuntar la camara al cuadrito con el celular');

    expect($hits->pluck('slug'))->toContain('conectar-whatsapp');
});

test('the help survives the embeddings provider being down', function (): void {
    $embedder = mock(KnowledgeEmbedder::class);
    $embedder->shouldReceive('embedOne')->andThrow(new RuntimeException('offline'));
    app()->instance(KnowledgeEmbedder::class, $embedder);

    expect(app(HelpFinder::class)->find(null, 'flibberwocky zarandajo ningunalado'))->toBeEmpty();
});

test('the admin inbox names the articles that are not answering', function (): void {
    $article = HelpArticle::query()->where('slug', 'conectar-whatsapp')->sole();
    $article->forceFill(['unhelpful_count' => 4, 'helpful_count' => 1])->saveQuietly();

    HelpArticle::query()->where('slug', 'cambiar-plan')->sole()
        ->forceFill(['helpful_count' => 9, 'unhelpful_count' => 1])->saveQuietly();

    $failing = HelpArticle::failing();

    expect($failing->pluck('slug')->all())->toBe(['conectar-whatsapp']);
});

test('opening an answer is counted, and closing it again is not', function (): void {
    $article = HelpArticle::query()->where('slug', 'conectar-whatsapp')->sole();

    Livewire::actingAs($this->user)
        ->test('help.index')
        ->call('toggle', $article->id)
        ->call('toggle', $article->id)
        ->call('toggle', $article->id);

    // Two opens, not three toggles: closing is not reading again.
    expect($article->fresh()->opened_count)->toBe(2);
});

test('the deflection number counts the reports that came after reading', function (): void {
    $article = HelpArticle::query()->where('slug', 'conectar-whatsapp')->sole();
    $article->forceFill(['opened_count' => 7])->saveQuietly();

    SupportTicket::factory()->for($this->business)->create(['after_help' => true]);
    SupportTicket::factory()->for($this->business)->create(['after_help' => false]);

    $this->actingAs($this->user);

    expect(HelpArticle::deflection())->toBe(['opened' => 7, 'tickets' => 1]);
});

test('a report handed over by an article is marked as a deflection that failed', function (): void {
    Livewire::actingAs($this->user)
        ->test('support.widget')
        ->call('open', 'whatsapp', 'Cómo conecto mi WhatsApp')
        ->assertSet('form.afterHelp', true)
        ->set('form.body', 'Hice los cuatro pasos y el codigo no me toma nunca')
        ->call('send');

    expect(SupportTicket::query()->firstOrFail()->after_help)->toBeTrue();
});

test('an article nobody touched in months asks to be reviewed', function (): void {
    $article = HelpArticle::query()->where('slug', 'ver-mi-plan')->sole();
    $article->forceFill(['updated_at' => now()->subMonths(8)])->saveQuietly();

    expect(HelpArticle::stale()->pluck('slug')->all())->toBe(['ver-mi-plan']);
});

test('the palette reaches the help from any screen', function (): void {
    $this->seed(MenuSeeder::class);
    $this->user->assignRole('client');
    $this->actingAs($this->user->refresh());

    $groups = app(GlobalSearch::class)->find('conecto');

    expect($groups->get('search.groups.help'))->not->toBeNull()
        ->and($groups->get('search.groups.help')->first()->title)->toBe('Cómo conecto mi WhatsApp');
});

test('an invited agent reads the help too: it holds no business data', function (): void {
    $this->seed(MenuSeeder::class);

    $agent = User::factory()->create();
    $agent->syncRoles(['agent']);
    $agent->business()->associate($this->business)->save();

    $this->actingAs($agent->refresh())->get('/ayuda')->assertOk();
});

/*
| An article that points at a screen which does not exist is never offered on
| the screen it is about, and nothing fails: it just stops helping. The base
| holds our documentation, so the seeded set is what gets checked.
*/
test('every article points at a screen that exists and a category that has a name', function (): void {
    $articles = HelpArticle::query()->get(['slug', 'screen', 'category']);

    expect($articles)->not->toBeEmpty();

    foreach ($articles as $article) {
        if ($article->screen !== null) {
            expect(Route::has($article->screen))->toBeTrue("{$article->slug} points at the unknown screen '{$article->screen}'");
        }

        expect(Lang::has('help.categories.'.$article->category))->toBeTrue("{$article->slug} sits in the unnamed category '{$article->category}'");
    }
});

test('an article is written the way that deflects a ticket: the answer first, then one step per line', function (): void {
    foreach (HelpArticle::query()->get() as $article) {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $article->body))));

        expect($lines[0])->not->toMatch('/^\d+\./', "{$article->slug} opens with a step and not with the answer")
            ->and(collect($lines)->filter(fn (string $line): bool => (bool) preg_match('/^\d+\. /', $line))->count())
            ->toBeGreaterThanOrEqual(2, "{$article->slug} has no steps to follow")
            ->and($article->title)->not->toEndWith('?', "{$article->slug}: the title is the task as she would type it, not a question mark");
    }
});
