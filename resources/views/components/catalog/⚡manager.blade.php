<?php

use App\Models\CatalogForm;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Catálogos del sistema')] class extends Component {
    /** Id of the open master (a catalog_forms row); null means none is open. */
    public ?int $selectedId = null;

    /**
     * Opens a master: the editor renders its form and the rail collapses.
     */
    public function select(int $id): void
    {
        $this->selectedId = $id;
    }

    /**
     * Closes the open master: back to the empty state and the rail expands.
     */
    public function close(): void
    {
        $this->selectedId = null;
    }

    /**
     * Active masters visible to the user, filtered by permission_key. The
     * super-admin goes through Gate::before, so they see every one.
     *
     * @return \Illuminate\Support\Collection<int, CatalogForm>
     */
    #[Computed]
    public function forms(): \Illuminate\Support\Collection
    {
        return CatalogForm::query()->where('is_active', true)->orderBy('order')->get()->filter(fn(CatalogForm $form): bool => $form->permission_key === null || (bool) auth()->user()?->can($form->permission_key))->values();
    }

    /**
     * Masters grouped for the rail listing.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, CatalogForm>>
     */
    #[Computed]
    public function grouped(): \Illuminate\Support\Collection
    {
        return $this->forms->groupBy('group');
    }

    /** The open master, or null when none is. */
    #[Computed]
    public function current(): ?CatalogForm
    {
        if ($this->selectedId === null) {
            return null;
        }

        return $this->forms->firstWhere('id', $this->selectedId);
    }

    /**
     * Name of the Livewire component the editor renders. Falls back to the
     * "under construction" placeholder while the master has no editor yet.
     */
    #[Computed]
    public function editorComponent(): ?string
    {
        $current = $this->current;

        if ($current === null) {
            return null;
        }

        return $this->editorExists($current->component) ? $current->component : 'catalog.placeholder';
    }

    /**
     * Whether the editor SFC exists. Maps the dotted Livewire name to its
     * `⚡<segment>.blade.php` file under resources/views/components.
     */
    private function editorExists(string $name): bool
    {
        $segments = explode('.', $name);
        $file = array_pop($segments);
        $dir = implode('/', $segments);

        return is_file(resource_path("views/components/{$dir}/⚡{$file}.blade.php"));
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('catalog.hub.title') }}</h1>
            <p class="page-head-sub">{{ __('catalog.hub.subtitle') }}</p>
        </div>
    </div>

    <div class="catalog-hub @if ($this->current) is-collapsed @endif">
        {{-- Panel 1: the master rail, data-driven from the table. It collapses to
        icons while a master is open and expands again on closing — hovering
        does not change the width. --}}
        <div class="catalog-rail-slot">
            <nav class="card catalog-list" aria-label="{{ __('catalog.hub.rail_label') }}"
                x-data="catalogRail({ titles: {{ \Illuminate\Support\Js::from($this->forms->pluck('title')->all()) }} })">

                {{-- A fixed header, so the search box does not scroll away. It filters
                client-side, so typing fires no request, and it hides by CSS once the
                rail collapses to icons. --}}
                <div class="catalog-rail-search">
                    <x-inputsform.input name="catalog-search" size="s" icon="search"
                        :placeholder="__('catalog.hub.search_placeholder')"
                        :aria-label="__('catalog.hub.search_label')" x-model="q" />
                </div>

                {{-- The body: a bounded height with its own scroll. The rail used to grow
                with the number of masters and stretch the whole screen. --}}
                <div class="catalog-rail-body">
                    @forelse ($this->grouped as $group => $items)
                        <div class="catalog-group"
                            x-show="groupVisible({{ \Illuminate\Support\Js::from($items->pluck('title')->all()) }})">
                            <p class="catalog-group-label">{{ $group }}</p>
                            <div class="catalog-group-rule" aria-hidden="true"></div>
                            @foreach ($items as $form)
                                <button type="button" wire:click="select({{ $form->id }})"
                                    x-on:click="clearSearch()" class="catalog-item" title="{{ $form->title }}"
                                    x-show="matches({{ \Illuminate\Support\Js::from($form->title) }})"
                                    @if ($selectedId === $form->id) aria-current="true" @endif>
                                    <span class="catalog-item-icon"><x-icon :name="$form->icon ?? 'library'" :size="18" /></span>
                                    <span class="catalog-item-text">
                                        {{-- The server paints the plain title, so nothing
                                             flickers before Alpine boots; the split
                                             version takes over only while searching. --}}
                                        <span x-show="! searching()">{{ $form->title }}</span>

                                        <span x-show="searching()" x-cloak>
                                            <template x-for="(part, i) in segments({{ \Illuminate\Support\Js::from($form->title) }})" :key="i">
                                                <span x-text="part.text" :class="part.hit && 'catalog-item-hit'"></span>
                                            </template>
                                        </span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @empty
                        <p class="catalog-group-label">{{ __('catalog.hub.none') }}</p>
                    @endforelse

                    <p class="catalog-rail-empty" x-show="!hasResults()" x-cloak>
                        {{ __('catalog.hub.no_matches') }}
                    </p>
                </div>
            </nav>
        </div>

        {{-- Panel 2: the open master's editor, a dynamic component. --}}
        <section class="card catalog-panel" wire:key="panel-{{ $selectedId ?? 'none' }}">
            @if ($this->current)
                <div class="catalog-panel-head">
                    <div class="catalog-panel-head-text">
                        <h2>
                            <span class="catalog-panel-icon"><x-icon :name="$this->current->icon ?? 'library'" :size="18" /></span>
                            {{ $this->current->title }}
                        </h2>
                        <p>{{ $this->current->description }}</p>
                    </div>
                    <button type="button" class="catalog-panel-close" wire:click="close" aria-label="{{ __('catalog.hub.close') }}">
                        <x-icon name="x" :size="18" />
                    </button>
                </div>

                <livewire:dynamic-component :is="$this->editorComponent" :wire:key="'editor-'.$this->current->id" lazy />
            @else
                <div class="catalog-empty">
                    <x-icon name="library" :size="32" />
                    <p class="catalog-empty-title">{{ __('catalog.hub.empty_title') }}</p>
                    <p class="catalog-empty-body">{{ __('catalog.hub.empty_body') }}</p>
                </div>
            @endif
        </section>
    </div>
</div>
