<?php

use App\Models\CatalogForm;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Catálogos del sistema')] class extends Component {
    /** Id del maestro abierto (fila de catalog_forms); null = ninguno abierto. */
    public ?int $selectedId = null;

    /**
     * Abrir un maestro: el editor renderiza su formulario y el riel se compacta.
     */
    public function select(int $id): void
    {
        $this->selectedId = $id;
    }

    /**
     * Cerrar el maestro abierto: vuelve al estado vacío y el riel se expande.
     */
    public function close(): void
    {
        $this->selectedId = null;
    }

    /**
     * Maestros activos visibles para el usuario (filtrados por permission_key).
     * El super-admin pasa por Gate::before, así que ve todos.
     *
     * @return \Illuminate\Support\Collection<int, CatalogForm>
     */
    #[Computed]
    public function forms(): \Illuminate\Support\Collection
    {
        return CatalogForm::query()->where('is_active', true)->orderBy('order')->get()->filter(fn(CatalogForm $form): bool => $form->permission_key === null || (bool) auth()->user()?->can($form->permission_key))->values();
    }

    /**
     * Maestros agrupados para la lista del riel.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, CatalogForm>>
     */
    #[Computed]
    public function grouped(): \Illuminate\Support\Collection
    {
        return $this->forms->groupBy('group');
    }

    /** El maestro abierto, o null si no hay ninguno. */
    #[Computed]
    public function current(): ?CatalogForm
    {
        if ($this->selectedId === null) {
            return null;
        }

        return $this->forms->firstWhere('id', $this->selectedId);
    }

    /**
     * Nombre del componente Livewire a renderizar en el editor. Si el editor del
     * maestro aún no existe, cae al placeholder "en construcción".
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
     * ¿Existe el SFC del editor? Mapea el nombre Livewire (con puntos) a su
     * archivo `⚡<segmento>.blade.php` bajo resources/views/components.
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
        {{-- Panel 1: riel de maestros (data-driven desde catalog_forms).
             Se compacta a iconos mientras hay un maestro abierto y solo se
             re-expande al cerrarlo (el hover no cambia el ancho). --}}
        <div class="catalog-rail-slot">
            <nav class="card catalog-list" aria-label="{{ __('catalog.hub.rail_label') }}"
                x-data="catalogRail({ titles: {{ \Illuminate\Support\Js::from($this->forms->pluck('title')->all()) }} })">

                {{-- Cabecera fija: el buscador no se va con el scroll de la lista.
                     Filtra client-side (los maestros ya están renderizados), así que
                     tipear no dispara un request. Con un maestro abierto el riel se
                     colapsa a iconos y esta cabecera se esconde por CSS. --}}
                <div class="catalog-rail-search">
                    <x-inputsform.input name="catalog-search" size="s" icon="search"
                        :placeholder="__('catalog.hub.search_placeholder')"
                        :aria-label="__('catalog.hub.search_label')" x-model="q" />
                </div>

                {{-- Cuerpo: alto acotado por --rail-scroll-h, con su propio scroll.
                     Antes el riel crecía con la cantidad de maestros y estiraba la
                     pantalla entera. --}}
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
                                    <span class="catalog-item-text">{{ $form->title }}</span>
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

        {{-- Panel 2: editor del maestro abierto (componente dinámico) --}}
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
