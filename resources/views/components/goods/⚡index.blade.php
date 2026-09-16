<?php

use App\Classes\Main\Client;
use App\Classes\Main\ServiceMenu;
use App\Enums\NotificationType;
use App\Livewire\Forms\Client\ServiceCategoryForm;
use App\Livewire\Forms\Client\ServiceForm;
use App\Models\BusinessActivity;
use App\Models\Service;
use App\Traits\HasNotifications;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Tus servicios" — the real screen over the tenant's rows. It keeps the
 * Fresha/Shopify split the mock-up blessed: the LIST is for finding and
 * operating (search, filter, load more), the SHEET edits over it. State,
 * validation and saves live in the Forms; queries in the ServiceMenu piece.
 */
new class extends Component
{
    use HasNotifications;

    public ServiceForm $form;

    public ServiceCategoryForm $categoryForm;

    public string $search = '';

    public string $filter = 'all';

    /** Rows on screen; "load more" grows it by a page. */
    public int $visible = 8;

    /** Open sheet: null closed, the service editor or the category one. */
    public ?string $sheet = null;

    /** The category sheet was born from the service sheet: go back to it. */
    public bool $returnToService = false;

    /** @var list<string> Groups folded away; the header stays visible. */
    public array $collapsed = [];

    public function mount(): void
    {
        // The Wireable DTO must exist before hydration, or it type-errors.
        $this->form->setup();
    }

    private function menu(): ?ServiceMenu
    {
        return Client::for(Auth::user())->serviceMenu;
    }

    /** @return Collection<int, Service> */
    #[Computed]
    public function services(): Collection
    {
        return $this->menu()?->services ?? new Collection;
    }

    /** @return Collection<int, \App\Models\ServiceCategory> */
    #[Computed]
    public function categories(): Collection
    {
        return $this->menu()?->categories ?? new Collection;
    }

    /**
     * Search and state filter over the full set, ORIGINAL KEYS KEPT: the
     * windows slice positions, the rows themselves carry the model.
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function filtered(): Collection
    {
        $needle = Str::ascii(mb_strtolower(trim($this->search)));

        return $this->services->filter(function (Service $service) use ($needle): bool {
            if ($this->filter === 'active' && ! $service->is_active) {
                return false;
            }
            if ($this->filter === 'paused' && $service->is_active) {
                return false;
            }

            return $needle === '' || str_contains(Str::ascii(mb_strtolower($service->name)), $needle);
        });
    }

    /**
     * Groups only rest state, and only once a shelf EXISTS: searching or
     * filtering flattens them, and a business yet to create its first
     * category gets a plain list instead of one lonely "Sin categoría".
     */
    #[Computed]
    public function grouped(): bool
    {
        return trim($this->search) === '' && $this->filter === 'all' && $this->categories->isNotEmpty();
    }

    /** @return list<Service> The flat window, destacados leading as promised. */
    #[Computed]
    public function flatRows(): array
    {
        return $this->filtered->sortByDesc('is_featured')->values()->take($this->visible)->all();
    }

    /**
     * The grouped view: Destacados pinned first (WhatsApp collections repeat
     * a product across collections, so featured rows also keep their shelf),
     * then the client's shelves in drag order. A collapsed group keeps its
     * header and hides its rows; groups past the window come with "load more".
     *
     * @return list<array{key: string, name: string, count: int, collapsed: bool, featured: bool, rows: list<Service>}>
     */
    #[Computed]
    public function groups(): array
    {
        $buckets = [['_featured', __('client.services.featured_title'), $this->services->filter(fn (Service $service): bool => $service->is_featured)]];

        foreach ($this->categories as $category) {
            $buckets[] = [(string) $category->id, $category->name, $this->services->where('service_category_id', $category->id)];
        }

        $buckets[] = ['', __('client.services.uncategorized'), $this->services->whereNull('service_category_id')];

        $groups = [];
        $shown = 0;

        foreach ($buckets as [$key, $name, $members]) {
            if ($members->isEmpty()) {
                continue;
            }

            if ($shown >= $this->visible) {
                break;
            }

            $collapsed = in_array($key, $this->collapsed, true);
            $rows = [];

            if (! $collapsed) {
                foreach ($members as $service) {
                    if ($shown >= $this->visible) {
                        break;
                    }

                    $rows[] = $service;
                    $shown++;
                }
            }

            $groups[] = [
                'key' => $key,
                'name' => $name,
                'count' => $members->count(),
                'collapsed' => $collapsed,
                'featured' => $key === '_featured',
                'rows' => $rows,
            ];
        }

        return $groups;
    }

    /** Rows actually on screen — what the load-more footer counts. */
    #[Computed]
    public function shownRows(): int
    {
        if (! $this->grouped) {
            return count($this->flatRows);
        }

        return array_sum(array_map(fn (array $group): int => count($group['rows']), $this->groups));
    }

    /** Loadable rows: collapsed groups keep theirs out of the count. */
    #[Computed]
    public function listTotal(): int
    {
        if (! $this->grouped) {
            return $this->filtered->count();
        }

        $featured = in_array('_featured', $this->collapsed, true)
            ? 0
            : $this->services->filter(fn (Service $service): bool => $service->is_featured)->count();

        return $featured + $this->services->filter(
            fn (Service $service): bool => ! in_array((string) ($service->service_category_id ?? ''), $this->collapsed, true),
        )->count();
    }

    public function toggleGroup(string $category): void
    {
        $this->collapsed = in_array($category, $this->collapsed, true)
            ? array_values(array_diff($this->collapsed, [$category]))
            : [...$this->collapsed, $category];
    }

    /** wire:sort drops a dragged shelf at its new position, persisted. */
    public function reorderCategories(int $category, int $position): void
    {
        $ids = $this->categories->pluck('id')->all();

        if (! in_array($category, $ids, true)) {
            return;
        }

        $order = array_values(array_diff($ids, [$category]));
        array_splice($order, max(0, $position), 0, [$category]);

        $this->menu()?->reorderCategories($order);
        $this->refreshList();
    }

    /** Pauses or resumes from the row; the badge flipping is the feedback. */
    public function toggle(int $id): void
    {
        $this->menu()?->toggle($id);
        $this->refreshList();
    }

    /** How many rows have their price resolved — what the meter reads. */
    #[Computed]
    public function priced(): int
    {
        return $this->services->filter(fn (Service $service): bool => $service->hasResolvedPrice())->count();
    }

    public function loadMore(): void
    {
        $this->visible += 8;
    }

    /** A new search or filter restarts the window at the top. */
    public function updatedSearch(): void
    {
        $this->visible = 8;
    }

    public function updatedFilter(): void
    {
        $this->visible = 8;
    }

    public function edit(int $id): void
    {
        $this->form->setup($id);
        $this->returnToService = false;
        $this->sheet = 'service';
    }

    public function add(): void
    {
        $this->form->setup();
        $this->returnToService = false;
        $this->sheet = 'service';
    }

    /** A trade chip opens the blank sheet with the name already typed. */
    public function addFromSuggestion(string $name): void
    {
        $this->form->setup(name: $name);
        $this->returnToService = false;
        $this->sheet = 'service';
    }

    public function openCategorySheet(): void
    {
        $this->categoryForm->reset();
        $this->categoryForm->resetErrorBag();
        $this->returnToService = false;
        $this->sheet = 'category';
    }

    /**
     * "Nueva categoría" inside the service sheet detours to the category one
     * WITHOUT losing what was typed: the service form stays hydrated and the
     * save trip comes back to it (Fresha's create-in-place).
     */
    public function openCategoryFromSheet(): void
    {
        $this->categoryForm->reset();
        $this->categoryForm->resetErrorBag();
        $this->returnToService = true;
        $this->sheet = 'category';
    }

    /** Closing the detour lands back on the service sheet, never on nothing. */
    public function closeSheet(): void
    {
        if ($this->sheet === 'category' && $this->returnToService) {
            $this->returnToService = false;
            $this->sheet = 'service';

            return;
        }

        $this->sheet = null;
    }

    public function saveService(): void
    {
        $notification = $this->form->save();
        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Error) {
            $this->sheet = null;
            $this->refreshList();
        }
    }

    public function saveCategory(): void
    {
        $notification = $this->categoryForm->save();
        $this->dispatchNotification($notification);

        if ($notification->type === NotificationType::Error) {
            return;
        }

        $this->refreshList();

        // Born from the service sheet: hand the new shelf straight to it.
        if ($this->returnToService) {
            $this->form->data->service_category_id = $this->categoryForm->savedId;
            $this->returnToService = false;
            $this->sheet = 'service';

            return;
        }

        $this->sheet = null;
    }

    /** Busts every computed that reads the tenant's rows after a write. */
    private function refreshList(): void
    {
        unset($this->services, $this->categories, $this->filtered, $this->flatRows, $this->groups, $this->shownRows, $this->listTotal, $this->priced);
    }

    /**
     * The trade's suggested names — the same vocabulary the wizard chips
     * speak, straight from the signed-in business's primary activity. No
     * activity yet simply means no chips; never a hardcoded list.
     *
     * @return list<string>
     */
    #[Computed]
    public function suggestions(): array
    {
        $activity = Auth::user()?->business?->primaryActivity();

        return $activity === null ? [] : BusinessActivity::suggestionsName(code: $activity->code);
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('client.services.title') }}</h1>
            <p class="page-head-sub">{{ __('client.services.sub') }}</p>
        </div>
    </div>

    @if ($this->services->isEmpty())
        <x-client.offer-empty
            icon="briefcase"
            :title="__('client.services.empty_title')"
            :body="__('client.services.empty_body')"
        >
            <x-ui.button variant="primary" size="sm" icon="plus" wire:click="add">{{ __('client.services.add') }}</x-ui.button>
            @if ($this->suggestions !== [])
                <p class="wizard-suggest">{{ __('client.services.suggestions') }}</p>
                <div class="wizard-chips">
                    @foreach ($this->suggestions as $suggestion)
                        <button type="button" class="wizard-chip" wire:click="addFromSuggestion(@js($suggestion))">{{ $suggestion }}</button>
                    @endforeach
                </div>
            @endif
        </x-client.offer-empty>
    @else
        {{-- The assistant offers to write; it never blocks the manual path. --}}
        <x-ui.ai-banner
            class="mb-3"
            :title="__('client.services.ai_title')"
            :body="__('client.services.ai_body')"
            :action="__('client.services.ai_action')"
        />

        {{-- ONE surface: toolbar to find, meter to nudge, list to act. The
        sheet lives in the slide-over — the list never breaks apart. --}}
        <x-ui.card class="p-5">
            {{-- A declared row: the search absorbs the slack so the toolbar
            always reaches the right edge (golden rule, formularios §5). --}}
            <x-catalog.form-row>
                <x-inputsform.input span="long" name="list_search" icon="search"
                    wire:model.live.debounce.300ms="search"
                    :aria-label="__('client.services.search_placeholder')"
                    :placeholder="__('client.services.search_placeholder')" />
                <x-inputsform.combobox span="short" name="filter" wire:model.live="filter"
                    :value="$filter" :placeholder="__('client.services.filter_label')" :options="[
                        'all' => __('client.services.filter_all'),
                        'active' => __('client.services.filter_active'),
                        'paused' => __('client.services.filter_paused'),
                    ]" />
                <div class="flex flex-none items-center gap-3 self-center">
                    <x-ui.button variant="secondary" size="sm" icon="plus" wire:click="openCategorySheet">{{ __('client.services.add_category') }}</x-ui.button>
                    <x-ui.button variant="primary" size="sm" icon="plus" wire:click="add">{{ __('client.services.add') }}</x-ui.button>
                </div>
            </x-catalog.form-row>

            {{-- Per-row completeness, GBP-style: it nudges, it never blocks. --}}
            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-[color:var(--border-subtle)] pb-3">
                <p class="font-mono text-sm text-subtle">{{ trans_choice('client.services.count', $this->filtered->count(), ['count' => $this->filtered->count()]) }}</p>
                <p class="text-sm text-muted">{{ __('client.services.meter', ['done' => $this->priced, 'total' => $this->services->count()]) }}</p>
                <div class="h-1.5 min-w-24 max-w-40 flex-1 overflow-hidden rounded-full bg-sunken">
                    <div class="h-full rounded-full bg-[color:var(--brand)]" style="width: {{ $this->services->isNotEmpty() ? round($this->priced / $this->services->count() * 100) : 0 }}%"></div>
                </div>
            </div>

            @if ($this->grouped)
                {{-- The shelves: Destacados pinned first, then the client's
                categories, draggable by the grip (their order is the order
                the assistant offers in). --}}
                <ul wire:sort="reorderCategories">
                    @foreach ($this->groups as $group)
                        <li
                            @if (! $group['featured'] && $group['key'] !== '') wire:sort:item="{{ $group['key'] }}" @endif
                            wire:key="group-{{ $group['key'] }}"
                            class="border-b border-[color:var(--border-subtle)] py-1 last:border-0"
                        >
                            <div class="flex items-center gap-1">
                                @if ($group['featured'])
                                    <span class="px-1"><x-icon name="star" :size="16" style="color:var(--brand)" /></span>
                                @elseif ($group['key'] === '')
                                    <span class="px-1 text-subtle"><x-icon name="folder" :size="16" /></span>
                                @else
                                    <span wire:sort:handle class="cursor-grab px-1 text-subtle"><x-icon name="grip-vertical" :size="16" /></span>
                                @endif
                                <button type="button" wire:click="toggleGroup('{{ $group['key'] }}')"
                                    class="flex flex-1 items-center gap-2 rounded-lg px-1 py-2 transition hover:bg-sunken"
                                    aria-expanded="{{ $group['collapsed'] ? 'false' : 'true' }}">
                                    <x-icon name="chevron-down" :size="16" class="{{ $group['collapsed'] ? '-rotate-90' : '' }} transition-transform" />
                                    <span class="text-sm font-bold text-strong">{{ $group['name'] }}</span>
                                    <span class="rounded-full bg-sunken px-2 py-0.5 font-mono text-xs text-subtle">{{ $group['count'] }}</span>
                                </button>
                            </div>
                            @if ($group['rows'] !== [])
                                <ul class="divide-y divide-[color:var(--border-subtle)]">
                                    @foreach ($group['rows'] as $service)
                                        <x-goods.row :service="$service" wire:key="row-{{ $group['key'] }}-{{ $service->id }}" />
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <x-ui.load-more :shown="$this->shownRows" :total="$this->listTotal" action="loadMore" />
            @elseif ($this->flatRows === [])
                <p class="py-6 text-center text-sm text-muted">{{ __('client.services.no_results') }}</p>
            @else
                {{-- Searching or filtering flattens the shelves: hits stand alone. --}}
                <ul class="divide-y divide-[color:var(--border-subtle)]">
                    @foreach ($this->flatRows as $service)
                        <x-goods.row :service="$service" :needle="$search" wire:key="flat-{{ $service->id }}" />
                    @endforeach
                </ul>

                <x-ui.load-more :shown="$this->shownRows" :total="$this->listTotal" action="loadMore" />
            @endif
        </x-ui.card>
    @endif

    {{-- The sheet slides over the list (Fresha pattern): editing never loses
    the place in a long list. --}}
    @if ($sheet === 'service')
        <x-ui.slide-over
            x-on:slide-over-close="$wire.closeSheet()"
            :title="$form->editingId !== null ? __('client.services.sheet_title', ['name' => $form->data->name]) : __('client.services.sheet_new_title')"
            :subtitle="__('client.services.sheet_hint')"
        >
            <div class="flex flex-col gap-4">
                <x-catalog.form-row>
                    <x-inputsform.input span="full" name="name" :label="__('client.services.field_name')"
                        wire:model="form.data.name" :value="$form->data->name" />
                </x-catalog.form-row>
                {{-- No shelves yet means nothing to choose: the select waits
                until the first category exists; creating one never needs to
                leave the sheet — the detour returns with it picked. --}}
                <div>
                    @if ($this->categories->isNotEmpty())
                        <x-catalog.form-row>
                            <x-inputsform.combobox span="full" name="service_category_id" :label="__('client.services.field_category')"
                                wire:model="form.data.service_category_id"
                                :value="$form->data->service_category_id"
                                :options="$this->categories->pluck('name', 'id')->put('', __('client.services.uncategorized'))->all()" />
                        </x-catalog.form-row>
                    @endif
                    <x-ui.button variant="ghost" size="sm" icon="plus" class="{{ $this->categories->isNotEmpty() ? 'mt-1' : '' }}"
                        wire:click="openCategoryFromSheet">{{ __('client.services.category_new_option') }}</x-ui.button>
                </div>
                <x-catalog.form-row>
                    <x-inputsform.input span="code" name="duration_minutes" :label="__('client.services.field_minutes')"
                        wire:model="form.data.duration_minutes"
                        :value="$form->data->duration_minutes" class="font-mono" inputmode="numeric" />
                    <x-inputsform.combobox span="text" name="price_type" :label="__('client.services.field_price_type')"
                        wire:model="form.data.price_type"
                        :value="$form->data->price_type" :options="[
                            'fixed' => __('client.services.price_fixed'),
                            'from' => __('client.services.price_from'),
                            'free' => __('client.services.price_free'),
                            'talk' => __('client.services.price_talk'),
                        ]" />
                </x-catalog.form-row>
                <x-catalog.form-row>
                    <x-inputsform.input span="short" name="price" :label="__('client.services.field_amount')"
                        wire:model="form.data.price"
                        :value="$form->data->price" class="font-mono" inputmode="numeric" />
                    <x-inputsform.input span="short" name="deposit" :label="__('client.services.field_deposit')"
                        :hint="__('client.services.deposit_help')"
                        wire:model="form.data.deposit"
                        :value="$form->data->deposit" class="font-mono" inputmode="numeric" />
                </x-catalog.form-row>
                <x-catalog.form-row>
                    <div class="f-full">
                        <x-ui.textarea name="description" :rows="2" :label="__('client.services.field_description')"
                            wire:model="form.data.description"
                            :hint="__('client.services.description_help')">{{ $form->data->description }}</x-ui.textarea>
                    </div>
                </x-catalog.form-row>
                <x-catalog.form-row>
                    <div class="f-full">
                        <x-ui.textarea name="prep_note" :rows="2" :label="__('client.services.field_prep')"
                            wire:model="form.data.prep_note"
                            :hint="__('client.services.prep_help')">{{ $form->data->prep_note }}</x-ui.textarea>
                    </div>
                </x-catalog.form-row>
                <x-ui.switch name="is_active" :label="__('client.services.offered')"
                    wire:model="form.data.is_active" :checked="$form->data->is_active" />
                <div>
                    <x-ui.switch name="is_featured" :label="__('client.services.featured')"
                        wire:model="form.data.is_featured" :checked="$form->data->is_featured" />
                    <p class="mt-1 text-sm text-muted">{{ __('client.services.featured_help') }}</p>
                </div>
            </div>

            <x-slot:footer>
                {{-- Cancel wears the danger colour by the owner's call
                (2026-09-15): the universal "watch out" cue, never a plain
                ghost that reads as loose text. --}}
                <x-ui.button variant="danger" size="sm" wire:click="closeSheet">{{ __('client.services.sheet_cancel') }}</x-ui.button>
                <span class="flex-1"></span>
                <x-ui.button variant="primary" size="sm" wire:click="saveService">{{ __('client.services.sheet_save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.slide-over>
    @elseif ($sheet === 'category')
        <x-ui.slide-over
            x-on:slide-over-close="$wire.closeSheet()"
            :title="__('client.services.category_sheet_title')"
            :subtitle="__('client.services.category_sheet_hint')"
        >
            <x-catalog.form-row>
                <x-inputsform.input span="full" name="name" :label="__('client.services.field_category_name')"
                    wire:model="categoryForm.name" :value="$categoryForm->name" />
            </x-catalog.form-row>

            <x-slot:footer>
                <x-ui.button variant="danger" size="sm" wire:click="closeSheet">{{ __('client.services.sheet_cancel') }}</x-ui.button>
                <span class="flex-1"></span>
                <x-ui.button variant="primary" size="sm" wire:click="saveCategory">{{ __('client.services.category_save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.slide-over>
    @endif
</div>
