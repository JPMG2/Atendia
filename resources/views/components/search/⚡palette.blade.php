<?php

use App\Classes\Search\Accents;
use App\Classes\Search\Highlight;
use App\Dto\SearchHitDto;
use App\Models\SearchRecent;
use App\Services\Search\GlobalSearch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The panel's global search. Two lanes behind it — words and meaning — fused
 * in {@see GlobalSearch}; this only holds the term and hands over rows.
 *
 * Groups keep the order config gives them and never reorder while typing:
 * spatial memory is the only thing that makes a list usable without a mouse.
 */
new class extends Component
{
    /** Enough to be useful, few enough that the list stays scannable. */
    private const int RECENTS = 5;

    /** Typing this first asks for things to DO, the way Notion and VS Code do. */
    private const string ACTION_PREFIX = '>';

    public string $term = '';

    /** Nothing is searched until it opens: the topbar must not cost a query. */
    public bool $opened = false;

    /** Where she is standing, so this screen's actions come first. */
    #[Locked]
    public ?string $screen = null;

    public function mount(?string $screen = null): void
    {
        $this->screen = $screen ?? request()->route()?->getName();
    }

    public function open(): void
    {
        $this->opened = true;

        // Reopening with the last search already typed is what lets her fix a
        // query instead of writing it again; the field selects it on focus, so
        // one keystroke still replaces the lot.
        $this->term = (string) session('search.last_term', '');
    }

    public function close(): void
    {
        session(['search.last_term' => trim($this->term)]);

        $this->opened = false;
        $this->term = '';
    }

    /**
     * What was picked, so the next open starts where the last one ended. In a
     * table and not the session: her trail has to be there from the phone too.
     *
     * @param  array{group:string,icon:string,title:string,subtitle:string,url:string,key:string}  $hit
     */
    public function pick(array $hit): mixed
    {
        $this->remember($hit);

        // The url arrives from the browser, so it is treated as input: only a
        // path of this app, never "//evil.example" and never a scheme.
        $url = (string) ($hit['url'] ?? '');

        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        return $this->redirect($url, navigate: true);
    }

    public function remember(array $hit): void
    {
        $user = Auth::user();

        if ($user?->business_id === null) {
            return;
        }

        SearchRecent::remember($user, new SearchHitDto(
            group: $hit['group'],
            icon: $hit['icon'],
            title: $hit['title'],
            subtitle: $hit['subtitle'],
            url: $hit['url'],
            key: $hit['key'],
        ));
    }

    /**
     * @return Collection<string, Collection<int, SearchHitDto>>
     */
    #[Computed]
    public function groups(): Collection
    {
        if (! $this->opened) {
            return collect();
        }

        $term = trim($this->term);

        if (str_starts_with($term, self::ACTION_PREFIX)) {
            return $this->actions(trim(mb_substr($term, 1)));
        }

        if ($term === '') {
            return $this->recents();
        }

        return app(GlobalSearch::class)->find($term, (int) config('atendia.search.per_group', 5));
    }

    /**
     * What the owner picked last, newest first.
     *
     * @return Collection<string, Collection<int, SearchHitDto>>
     */
    private function recents(): Collection
    {
        $user = Auth::user();

        if ($user?->business_id === null) {
            return collect();
        }

        $rows = SearchRecent::forUser($user)
            ->map(fn (SearchRecent $row): SearchHitDto => new SearchHitDto(
                group: 'search.groups.recent',
                icon: (string) $row->icon,
                title: (string) $row->title,
                subtitle: (string) ($row->subtitle ?? ''),
                url: (string) $row->url,
                key: (string) $row->hit_key,
            ));

        return $rows->isEmpty() ? collect() : collect(['search.groups.recent' => $rows]);
    }

    /**
     * Things to DO, filtered by what follows the prefix. Each one lands on the
     * screen that performs it with its own sheet already open — an action that
     * only navigates would be the dead button all over again.
     *
     * @return Collection<string, Collection<int, SearchHitDto>>
     */
    private function actions(string $term): Collection
    {
        $needle = Accents::fold(mb_strtolower($term));

        $rows = collect([
            ['key' => 'service', 'icon' => 'scissors', 'url' => route('my-services', ['nuevo' => 1]), 'on' => 'my-services'],
            ['key' => 'product', 'icon' => 'package', 'url' => route('my-products', ['nuevo' => 1]), 'on' => 'my-products'],
            ['key' => 'import', 'icon' => 'upload', 'url' => route('my-products'), 'on' => 'my-products'],
            ['key' => 'invite', 'icon' => 'users', 'url' => route('team'), 'on' => 'team'],
        ])
            // What belongs to the screen she is on comes first: the palette of
            // Linear reads the context before it reads the alphabet.
            ->sortBy(fn (array $row): int => $row['on'] === $this->screen ? 0 : 1)
            ->map(fn (array $row): SearchHitDto => new SearchHitDto(
                group: 'search.groups.actions',
                icon: $row['icon'],
                title: __('search.actions.'.$row['key']),
                subtitle: __('search.actions.hint'),
                url: $row['url'],
                key: SearchHitDto::keyFor('search.groups.actions', $row['key']),
            ))
            ->filter(fn (SearchHitDto $hit): bool => $needle === '' || str_contains(
                Accents::fold(mb_strtolower($hit->title)), $needle,
            ))
            ->values();

        $groups = $rows->isEmpty() ? collect() : collect(['search.groups.actions' => $rows]);

        $admin = $this->adminActions($needle);

        return $admin->isEmpty() ? $groups : $groups->put('search.groups.admin', $admin);
    }

    /**
     * The owner's own doors, in their own group so they never pass for the
     * business's. Gated by the permission, not by hiding the row.
     *
     * @return Collection<int, SearchHitDto>
     */
    private function adminActions(string $needle): Collection
    {
        if (Auth::user()?->can('access-admin-panel') !== true) {
            return collect();
        }

        return collect([
            ['key' => 'moderation', 'icon' => 'alert-triangle', 'route' => 'admin.moderation'],
            ['key' => 'payments', 'icon' => 'credit-card', 'route' => 'admin.payments'],
            ['key' => 'catalogs', 'icon' => 'package', 'route' => 'admin.catalogs'],
            ['key' => 'logs', 'icon' => 'book-open', 'route' => 'admin.logs'],
        ])
            ->map(fn (array $row): SearchHitDto => new SearchHitDto(
                group: 'search.groups.admin',
                icon: $row['icon'],
                title: __('search.admin.'.$row['key']),
                subtitle: __('search.admin.hint'),
                url: route($row['route']),
                key: SearchHitDto::keyFor('search.groups.admin', $row['key']),
            ))
            ->filter(fn (SearchHitDto $hit): bool => $needle === '' || str_contains(
                Accents::fold(mb_strtolower($hit->title)), $needle,
            ))
            ->values();
    }

    /** A question is for the assistant, not for a list of names. */
    #[Computed]
    public function looksLikeQuestion(): bool
    {
        $term = trim($this->term);

        if (mb_strlen($term) < 6 || str_starts_with($term, self::ACTION_PREFIX)) {
            return false;
        }

        return str_contains($term, '?')
            || preg_match('/^(que|qué|cuanto|cuánto|cuando|cuándo|como|cómo|quien|quién|donde|dónde|por que|por qué|cuales|cuáles)\b/iu', $term) === 1;
    }

    #[Computed]
    public function total(): int
    {
        return $this->groups->sum(fn (Collection $hits): int => $hits->count());
    }
};
?>

<div
    class="cmdk"
    x-data="{
        open: false,
        cursor: 0,
        /* The key she actually has: printing a Mac glyph to someone on Windows
           names a key that is not on their keyboard. */
        mod: /Mac|iPod|iPhone|iPad/.test(navigator.platform || navigator.userAgent) ? '⌘' : 'Ctrl ',
        show() {
            this.open = true;
            this.cursor = 0;
            const ready = $wire.opened ? Promise.resolve() : $wire.open();
            return Promise.resolve(ready).then(() => $nextTick(() => {
                const field = this.$refs.field;
                field?.focus();
                /* Selected, not just focused: the last search is a starting
                   point to correct, and one keystroke still wipes it. */
                field?.select?.();
            }));
        },
        hide() {
            this.open = false;
            $wire.close();
        },
        rows() {
            return Array.from(this.$refs.list?.querySelectorAll('[data-row]') ?? []);
        },
        move(step) {
            const rows = this.rows();
            if (! rows.length) { return; }
            this.cursor = (this.cursor + step + rows.length) % rows.length;
            rows[this.cursor]?.scrollIntoView({ block: 'nearest' });
        },
        go() {
            this.rows()[this.cursor]?.click();
        },
        /* Only 1-5 with the modifier, checked by hand: a blanket prevent on
           every meta key would swallow copy and paste inside the field. */
        jump(event) {
            if (! (event.metaKey || event.ctrlKey) || ! /^[1-5]$/.test(event.key)) { return; }
            const row = this.rows()[Number(event.key) - 1];
            if (! row) { return; }
            event.preventDefault();
            row.click();
        },
    }"
    x-on:keydown.window.prevent.cmd.k="show()"
    x-on:keydown.window.prevent.ctrl.k="show()"
>
    {{-- The trigger reads as the search field it replaces, so nothing moves
    in the topbar; the panel itself is what opens. --}}
    <button
        type="button"
        class="cmdk-trigger"
        x-on:click="show()"
        :aria-expanded="open"
        aria-haspopup="dialog"
        data-testid="cmdk-open"
    >
        <x-icon name="search" :size="18" />
        <span class="cmdk-trigger-text">{{ __('search.placeholder') }}</span>
        <kbd class="cmdk-kbd" x-text="mod + 'K'">⌘K</kbd>
    </button>

    {{-- The topbar is a containing block and would clip a fixed panel to its
    height, the same reason the bell and ask-atendia teleport. --}}
    @teleport('body')
        <div x-show="open" x-cloak>
            <div class="cmdk-backdrop" x-on:click="hide()"></div>

            <div
                class="cmdk-panel"
                role="dialog"
                aria-modal="true"
                x-on:keydown.escape.window="if (open) hide()"
                x-on:keydown.down.prevent="move(1)"
                x-on:keydown.up.prevent="move(-1)"
                x-on:keydown.enter.prevent="go()"
                x-on:keydown="jump($event)"
            >
                {{-- Focus never leaves this field: the arrows move the
                selection so typing can continue without a click. --}}
                <div class="cmdk-field">
                    <x-ui.input
                        name="cmdk_term"
                        type="search"
                        icon="search"
                        size="lg"
                        x-ref="field"
                        autocomplete="off"
                        wire:model.live.debounce.250ms="term"
                        x-on:input="cursor = 0"
                        :placeholder="__('search.placeholder')"
                        :aria-label="__('search.open')"
                        data-testid="cmdk-input"
                    />
                    <span class="cmdk-spin" wire:loading wire:target="term" aria-hidden="true"></span>
                    {{-- The phone has no backdrop to click and no footer to
                    read: without this the panel would be a trap. --}}
                    <x-ui.icon-button
                        icon="x"
                        size="sm"
                        variant="ghost"
                        class="cmdk-close"
                        :label="__('search.keys.close')"
                        x-on:click="hide()"
                        data-testid="cmdk-close"
                    />
                </div>

                <div class="cmdk-results" x-ref="list">
                    {{-- Runs across the groups, not inside them: the shortcut
                    numbers what the eye counts, which is the whole list. --}}
                    @php($shortcut = 1)
                    @forelse ($this->groups as $group => $hits)
                        <p class="cmdk-group">
                            {{ __($group) }}
                            <span class="cmdk-count font-mono">{{ $hits->count() }}</span>
                        </p>

                        @foreach ($hits as $hit)
                            <a
                                href="{{ $hit->url }}"
                                wire:navigate
                                class="cmdk-row"
                                data-row
                                wire:key="hit-{{ $hit->key }}"
                                {{-- One trip: the pick is written and THEN the
                                redirect happens. Firing both at once let the
                                navigation cancel the write. --}}
                                wire:click.prevent="pick({{ Js::from([
                                    'group' => $hit->group, 'icon' => $hit->icon, 'title' => $hit->title,
                                    'subtitle' => $hit->subtitle, 'url' => $hit->url, 'key' => $hit->key,
                                ]) }})"
                                x-on:click="hide()"
                                :class="rows()[cursor] === $el && 'is-cursor'"
                                x-on:mouseenter="cursor = rows().indexOf($el)"
                            >
                                <span class="cmdk-row-icon"><x-icon :name="$hit->icon" :size="16" /></span>
                                <span class="cmdk-row-text">
                                    {{-- Marked, not plain: the row says WHY it
                                    came back. Highlight escapes before it wraps. --}}
                                    <span class="cmdk-row-title">{{ $hit->semantic
                                        ? Highlight::markClosest($hit->title, $term)
                                        : Highlight::mark($hit->title, $term) }}</span>
                                    @if ($hit->subtitle !== '')
                                        {{-- A row found by meaning has no literal
                                        match, so the nearest word is marked as
                                        the guess it is. --}}
                                        <span class="cmdk-row-sub">{{ $hit->semantic
                                            ? Highlight::markClosest($hit->subtitle, $term)
                                            : Highlight::mark($hit->subtitle, $term) }}</span>
                                    @endif
                                </span>
                                @if ($hit->semantic)
                                    <span class="cmdk-row-tag">{{ __('search.semantic_tag') }}</span>
                                @endif
                                @if ($shortcut <= 5)
                                    <kbd class="cmdk-kbd cmdk-row-kbd" x-text="mod + '{{ $shortcut }}'">⌘{{ $shortcut }}</kbd>
                                @endif
                                @php($shortcut++)
                            </a>
                        @endforeach
                        @if ($loop->last && $this->looksLikeQuestion)
                            <x-search.ask-row :term="trim($term)" />
                        @endif
                    @empty
                        @if ($this->looksLikeQuestion)
                            <x-search.ask-row :term="trim($term)" />
                        @endif

                        <div class="cmdk-blank">
                            @if (trim($term) === '')
                                <x-ui.empty-state icon="search" :title="__('search.start_title')"
                                    :body="__('search.start_body')" compact />
                            @else
                                <x-ui.empty-state icon="search"
                                    :title="__('search.empty_title', ['term' => trim($term)])"
                                    :body="__('search.empty_body')" compact />
                            @endif
                        </div>
                    @endforelse
                </div>

                <div class="cmdk-foot">
                    <span class="cmdk-hint"><kbd class="cmdk-kbd">↑↓</kbd> {{ __('search.keys.move') }}</span>
                    <span class="cmdk-hint"><kbd class="cmdk-kbd">↵</kbd> {{ __('search.keys.open') }}</span>
                    <span class="cmdk-hint"><kbd class="cmdk-kbd">esc</kbd> {{ __('search.keys.close') }}</span>
                </div>
            </div>
        </div>
    @endteleport
</div>
