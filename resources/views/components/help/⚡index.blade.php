<?php

use App\Models\HelpArticle;
use App\Models\Menu;
use App\Services\Help\HelpFinder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Help that answers before anyone opens a ticket — and never stands in the way
 * of one. Gartner: only 14% of problems are fully solved in self-service, and
 * a bad channel switch burns the next attempt, so the report button is here
 * from the first second instead of hidden behind a search.
 */
new class extends Component
{
    #[Url(as: 'buscar', except: '')]
    public string $search = '';

    public ?int $open = null;

    /** Where she came from, so her screen's answers lead. */
    #[Url(as: 'desde', except: null)]
    public ?string $from = null;

    public array $rated = [];

    /**
     * @return SupportCollection<string, Collection<int, HelpArticle>>
     */
    #[Computed]
    public function groups(): SupportCollection
    {
        $term = trim($this->search);

        $articles = $term === ''
            ? HelpArticle::shelf()
            : app(HelpFinder::class)->find($this->from, $term, 50, wide: true);

        return $articles->groupBy('category');
    }

    /** Her own screen's answers, above everything else. */
    #[Computed]
    public function forHerScreen(): Collection
    {
        return trim($this->search) === '' && $this->from !== null
            ? HelpArticle::suggest($this->from)
            : new Collection;
    }

    public function toggle(int $id): void
    {
        if ($this->open === $id) {
            $this->open = null;

            return;
        }

        $this->open = $id;

        // Counted on the way in, not on the vote: most people never vote.
        HelpArticle::locate($id)?->markOpened();
    }

    /**
     * The thumb is counted and remembered for this visit. A thumb DOWN is the
     * one that matters, and it is also the moment to offer the report — the
     * way AWS pre-fills the case it is about to open.
     */
    public function rate(int $id, bool $helpful): void
    {
        $article = HelpArticle::locate($id);

        if ($article === null || array_key_exists($id, $this->rated)) {
            return;
        }

        $article->rate($helpful);
        $this->rated[$id] = $helpful;

        if (! $helpful) {
            $this->dispatch('support-open', screen: $article->screen, about: $article->title);
        }
    }

    public function screenName(?string $route): ?string
    {
        return $route === null ? null : Menu::titleFor($route);
    }
};
?>

<div>
    <x-ui.page-head :title="__('help.title')" :sub="__('help.sub')">
        {{-- Visible from the first second: hiding the way out is the first
        anti-pattern every support team names. --}}
        <x-ui.button variant="secondary" size="sm" icon="life-buoy"
            x-data x-on:click="$dispatch('support-open')" data-testid="help-report">
            {{ __('help.report') }}
        </x-ui.button>
    </x-ui.page-head>

    <x-ui.card class="p-5">
        <x-catalog.form-row>
            <x-inputsform.input
                span="long"
                name="help_search"
                icon="search"
                wire:model.live.debounce.300ms="search"
                :aria-label="__('help.search_placeholder')"
                :placeholder="__('help.search_placeholder')"
            />
        </x-catalog.form-row>

        @if ($this->forHerScreen->isNotEmpty())
            <p class="help-lead">{{ __('help.for_screen', ['screen' => $this->screenName($from)]) }}</p>
            <div class="help-list">
                @foreach ($this->forHerScreen as $article)
                    <x-help.article :article="$article" :open="$open === $article->id" :rated="$rated[$article->id] ?? null" />
                @endforeach
            </div>
        @endif

        @forelse ($this->groups as $category => $articles)
            <p class="help-lead">{{ __('help.categories.'.$category) }}</p>
            <div class="help-list">
                @foreach ($articles as $article)
                    <x-help.article :article="$article" :open="$open === $article->id" :rated="$rated[$article->id] ?? null" />
                @endforeach
            </div>
        @empty
            <x-ui.empty-state
                icon="search"
                :title="__('help.empty_title', ['term' => trim($search)])"
                :body="__('help.empty_body')"
                compact
            >
                <x-ui.button variant="primary" size="sm" icon="life-buoy"
                    x-data x-on:click="$dispatch('support-open')">
                    {{ __('help.report') }}
                </x-ui.button>
            </x-ui.empty-state>
        @endforelse
    </x-ui.card>
</div>
