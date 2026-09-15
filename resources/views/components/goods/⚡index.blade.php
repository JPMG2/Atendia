<?php

use App\Models\BusinessActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Tus servicios" — living mock-up, zero persistence. The screen follows the
 * Fresha/Shopify split: the LIST is for finding and operating (search, filter,
 * load more), the SHEET is for editing and slides over the list. The mock
 * carries enough rows to make the scale pattern visible.
 */
new class extends Component
{
    public string $mode = 'loaded';

    public string $search = '';

    public string $filter = 'all';

    /** Rows on screen; "load more" grows it by a page. */
    public int $visible = 8;

    /** Open sheet: null closed, -1 a new service, >= 0 the row index. */
    public ?int $editing = null;

    /**
     * Display order of the client's own categories (Fresha pattern; they map
     * to WhatsApp catalog collections later). Uncategorized rows close the
     * list under their own bucket.
     *
     * @var list<string>
     */
    public array $categories = ['Cortes', 'Barba', 'Color', 'Peinados', 'Tratamientos', 'Estética'];

    /** @var list<string> Groups folded away; the header stays visible. */
    public array $collapsed = [];

    /**
     * Mock rows a big barber shop would load: price and duration stay optional
     * on purpose — "ofrecemos, no obligamos". `prep` is what the client must
     * know or bring; `deposit` is the booking down payment.
     *
     * @var list<array{name: string, description: ?string, price: ?int, minutes: ?int, deposit: ?int, prep: ?string, category: ?string, featured: bool, active: bool}>
     */
    public array $services = [
        ['name' => 'Corte de caballero', 'description' => 'Tijera o máquina, con lavado incluido.', 'price' => 12000, 'minutes' => 30, 'deposit' => null, 'prep' => null, 'category' => 'Cortes', 'featured' => false, 'active' => true],
        ['name' => 'Corte y barba', 'description' => 'El combo completo, con toalla caliente.', 'price' => 16500, 'minutes' => 45, 'deposit' => 5000, 'prep' => 'Llega con el pelo seco; la toalla caliente hace el resto.', 'category' => 'Barba', 'featured' => true, 'active' => true],
        ['name' => 'Coloración', 'description' => null, 'price' => null, 'minutes' => 90, 'deposit' => null, 'prep' => 'No laves tu pelo el día anterior.', 'category' => 'Color', 'featured' => false, 'active' => true],
        ['name' => 'Peinado para eventos', 'description' => 'Se reserva con seña.', 'price' => 20000, 'minutes' => null, 'deposit' => 8000, 'prep' => null, 'category' => 'Peinados', 'featured' => false, 'active' => false],
        ['name' => 'Corte de dama', 'description' => 'Incluye lavado y secado.', 'price' => 15000, 'minutes' => 45, 'deposit' => null, 'prep' => null, 'category' => 'Cortes', 'featured' => false, 'active' => true],
        ['name' => 'Corte de niños', 'description' => 'Hasta 12 años.', 'price' => 9000, 'minutes' => 25, 'deposit' => null, 'prep' => null, 'category' => 'Cortes', 'featured' => false, 'active' => true],
        ['name' => 'Perfilado de barba', 'description' => 'Navaja y aceite.', 'price' => 8000, 'minutes' => 20, 'deposit' => null, 'prep' => null, 'category' => 'Barba', 'featured' => false, 'active' => true],
        ['name' => 'Afeitado clásico', 'description' => 'Toalla caliente y after shave.', 'price' => 10000, 'minutes' => 30, 'deposit' => null, 'prep' => null, 'category' => 'Barba', 'featured' => false, 'active' => true],
        ['name' => 'Mechas', 'description' => 'Con papel o gorra según el largo.', 'price' => null, 'minutes' => 120, 'deposit' => 10000, 'prep' => 'Trae fotos de referencia si tienes.', 'category' => 'Color', 'featured' => false, 'active' => true],
        ['name' => 'Alisado permanente', 'description' => null, 'price' => 45000, 'minutes' => 150, 'deposit' => 15000, 'prep' => 'No laves tu pelo 48 horas después.', 'category' => 'Tratamientos', 'featured' => true, 'active' => true],
        ['name' => 'Tratamiento capilar', 'description' => 'Hidratación profunda con ampolla.', 'price' => 18000, 'minutes' => 60, 'deposit' => null, 'prep' => null, 'category' => 'Tratamientos', 'featured' => false, 'active' => true],
        ['name' => 'Peinado con brushing', 'description' => null, 'price' => 12000, 'minutes' => 40, 'deposit' => null, 'prep' => null, 'category' => 'Peinados', 'featured' => false, 'active' => true],
        ['name' => 'Diseño de cejas', 'description' => 'Con navaja o cera.', 'price' => 5000, 'minutes' => 15, 'deposit' => null, 'prep' => null, 'category' => 'Estética', 'featured' => false, 'active' => true],
        ['name' => 'Limpieza facial', 'description' => 'Vapor, extracción y máscara.', 'price' => 16000, 'minutes' => 50, 'deposit' => null, 'prep' => 'Ven con la cara lavada, sin cremas.', 'category' => 'Estética', 'featured' => false, 'active' => true],
        ['name' => 'Tintura de barba', 'description' => null, 'price' => 7000, 'minutes' => 25, 'deposit' => null, 'prep' => null, 'category' => 'Barba', 'featured' => false, 'active' => true],
        ['name' => 'Corte con diseño', 'description' => 'Líneas y dibujos a máquina.', 'price' => 14000, 'minutes' => 40, 'deposit' => null, 'prep' => null, 'category' => 'Cortes', 'featured' => false, 'active' => true],
        ['name' => 'Rapado completo', 'description' => null, 'price' => 7000, 'minutes' => 15, 'deposit' => null, 'prep' => null, 'category' => 'Cortes', 'featured' => false, 'active' => true],
        ['name' => 'Manicura express', 'description' => 'Lima, cutícula y esmalte.', 'price' => 8000, 'minutes' => 30, 'deposit' => null, 'prep' => null, 'category' => 'Estética', 'featured' => false, 'active' => false],
        ['name' => 'Masaje capilar', 'description' => null, 'price' => null, 'minutes' => 20, 'deposit' => null, 'prep' => null, 'category' => 'Tratamientos', 'featured' => false, 'active' => true],
        ['name' => 'Botox capilar', 'description' => 'Repara puntas y da brillo.', 'price' => 30000, 'minutes' => 90, 'deposit' => 10000, 'prep' => null, 'category' => 'Tratamientos', 'featured' => false, 'active' => true],
        ['name' => 'Peinado de novia', 'description' => 'Incluye prueba previa.', 'price' => 55000, 'minutes' => null, 'deposit' => 20000, 'prep' => 'La prueba se agenda dos semanas antes.', 'category' => 'Peinados', 'featured' => true, 'active' => true],
        ['name' => 'Depilación de rostro', 'description' => null, 'price' => 6000, 'minutes' => 20, 'deposit' => null, 'prep' => null, 'category' => 'Estética', 'featured' => false, 'active' => false],
    ];

    /**
     * Search and state filter over the full set, ORIGINAL KEYS KEPT: the row
     * actions address the real index, never the position on screen.
     *
     * @return array<int, array{name: string, description: ?string, price: ?int, minutes: ?int, deposit: ?int, prep: ?string, category: ?string, featured: bool, active: bool}>
     */
    #[Computed]
    public function filtered(): array
    {
        $needle = Str::ascii(mb_strtolower(trim($this->search)));

        return array_filter($this->services, function (array $service) use ($needle): bool {
            if ($this->filter === 'active' && ! $service['active']) {
                return false;
            }
            if ($this->filter === 'paused' && $service['active']) {
                return false;
            }

            return $needle === '' || str_contains(Str::ascii(mb_strtolower($service['name'])), $needle);
        });
    }

    /** Groups only rest state: searching or filtering flattens the shelves. */
    #[Computed]
    public function grouped(): bool
    {
        return trim($this->search) === '' && $this->filter === 'all';
    }

    /** @return list<int> The flat window while searching or filtering. */
    #[Computed]
    public function flatRows(): array
    {
        return array_slice(array_keys($this->filtered), 0, $this->visible);
    }

    /**
     * The grouped view: Destacados pinned first (WhatsApp collections repeat
     * a product across collections, so featured rows also keep their shelf),
     * then the client's categories. A collapsed group keeps its header and
     * hides its rows; groups past the window appear as "load more" reaches.
     *
     * @return list<array{key: string, name: string, count: int, collapsed: bool, featured: bool, rows: list<int>}>
     */
    #[Computed]
    public function groups(): array
    {
        $buckets = [['_featured', __('client.services.featured_title'), array_keys(array_filter($this->services, fn (array $service): bool => $service['featured']))]];

        foreach ([...$this->categories, null] as $category) {
            $buckets[] = [
                $category ?? '',
                $category ?? __('client.services.uncategorized'),
                array_keys(array_filter($this->services, fn (array $service): bool => $service['category'] === $category)),
            ];
        }

        $groups = [];
        $shown = 0;

        foreach ($buckets as [$key, $name, $indexes]) {
            if ($indexes === []) {
                continue;
            }

            if ($shown >= $this->visible) {
                break;
            }

            $collapsed = in_array($key, $this->collapsed, true);
            $rows = [];

            if (! $collapsed) {
                foreach ($indexes as $index) {
                    if ($shown >= $this->visible) {
                        break;
                    }

                    $rows[] = $index;
                    $shown++;
                }
            }

            $groups[] = [
                'key' => $key,
                'name' => $name,
                'count' => count($indexes),
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
            return count($this->filtered);
        }

        $featured = in_array('_featured', $this->collapsed, true)
            ? 0
            : count(array_filter($this->services, fn (array $service): bool => $service['featured']));

        return $featured + count(array_filter(
            $this->services,
            fn (array $service): bool => ! in_array($service['category'] ?? '', $this->collapsed, true),
        ));
    }

    public function toggleGroup(string $category): void
    {
        $this->collapsed = in_array($category, $this->collapsed, true)
            ? array_values(array_diff($this->collapsed, [$category]))
            : [...$this->collapsed, $category];
    }

    /** wire:sort drops a dragged category at its new position. */
    public function reorderCategories(string $category, int $position): void
    {
        if (! in_array($category, $this->categories, true)) {
            return;
        }

        $order = array_values(array_diff($this->categories, [$category]));
        array_splice($order, max(0, $position), 0, [$category]);
        $this->categories = $order;
    }

    /** How many rows carry a price — what the completeness meter reads. */
    #[Computed]
    public function priced(): int
    {
        return count(array_filter($this->services, fn (array $service): bool => $service['price'] !== null));
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

    public function edit(int $index): void
    {
        $this->editing = $index;
    }

    public function add(): void
    {
        $this->editing = -1;
    }

    public function closeSheet(): void
    {
        $this->editing = null;
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

    public function switchTo(string $mode): void
    {
        $this->mode = in_array($mode, ['empty', 'loaded'], true) ? $mode : 'loaded';
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('client.services.title') }}</h1>
            <p class="page-head-sub">{{ __('client.services.sub') }}</p>
        </div>
        <div class="mock-switch" role="group" aria-label="{{ __('client.services.state_label') }}">
            <button type="button" wire:click="switchTo('empty')" @class(['is-active' => $mode === 'empty'])>
                {{ __('client.services.state_empty') }}
            </button>
            <button type="button" wire:click="switchTo('loaded')" @class(['is-active' => $mode === 'loaded'])>
                {{ __('client.services.state_loaded') }}
            </button>
        </div>
    </div>

    @if ($mode === 'empty')
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
                        <button type="button" class="wizard-chip">{{ $suggestion }}</button>
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
                    <x-ui.button variant="secondary" size="sm" icon="plus">{{ __('client.services.add_category') }}</x-ui.button>
                    <x-ui.button variant="primary" size="sm" icon="plus" wire:click="add">{{ __('client.services.add') }}</x-ui.button>
                </div>
            </x-catalog.form-row>

            {{-- Per-row completeness, GBP-style: it nudges, it never blocks. --}}
            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-[color:var(--border-subtle)] pb-3">
                <p class="font-mono text-sm text-subtle">{{ trans_choice('client.services.count', count($this->filtered), ['count' => count($this->filtered)]) }}</p>
                <p class="text-sm text-muted">{{ __('client.services.meter', ['done' => $this->priced, 'total' => count($services)]) }}</p>
                <div class="h-1.5 min-w-24 max-w-40 flex-1 overflow-hidden rounded-full bg-sunken">
                    <div class="h-full rounded-full bg-[color:var(--brand)]" style="width: {{ count($services) > 0 ? round($this->priced / count($services) * 100) : 0 }}%"></div>
                </div>
            </div>

            @if ($this->grouped)
                {{-- The shelves: Destacados pinned first, then the client's
                categories, draggable by the grip (their order is the order
                the assistant offers in). --}}
                <ul wire:sort="reorderCategories">
                    @foreach ($this->groups as $group)
                        <li
                            @if (! $group['featured']) wire:sort:item="{{ $group['key'] }}" @endif
                            wire:key="group-{{ $group['key'] }}"
                            class="border-b border-[color:var(--border-subtle)] py-1 last:border-0"
                        >
                            <div class="flex items-center gap-1">
                                @if ($group['featured'])
                                    <span class="px-1"><x-icon name="star" :size="16" style="color:var(--brand)" /></span>
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
                                    @foreach ($group['rows'] as $index)
                                        <x-goods.row :service="$services[$index]" :index="$index" wire:key="row-{{ $group['key'] }}-{{ $index }}" />
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
                    @foreach ($this->flatRows as $index)
                        <x-goods.row :service="$services[$index]" :index="$index" :needle="$search" wire:key="flat-{{ $index }}" />
                    @endforeach
                </ul>

                <x-ui.load-more :shown="$this->shownRows" :total="$this->listTotal" action="loadMore" />
            @endif
        </x-ui.card>
    @endif

    {{-- The sheet slides over the list (Fresha pattern): editing never loses
    the place in a long list. -1 is a blank sheet for a new service. --}}
    @if ($editing !== null)
        @php $sheet = $services[$editing] ?? ['name' => '', 'description' => null, 'price' => null, 'minutes' => null, 'deposit' => null, 'prep' => null, 'category' => null, 'featured' => false, 'active' => true]; @endphp
        <x-ui.slide-over
            x-on:slide-over-close="$wire.closeSheet()"
            :title="$editing >= 0 ? __('client.services.sheet_title', ['name' => $sheet['name']]) : __('client.services.sheet_new_title')"
            :subtitle="__('client.services.sheet_hint')"
        >
            <div class="flex flex-col gap-4">
                <x-catalog.form-row>
                    <x-inputsform.input span="full" name="sheet_name" :label="__('client.services.field_name')"
                        :value="$sheet['name']" />
                </x-catalog.form-row>
                <x-catalog.form-row>
                    <x-inputsform.combobox span="full" name="sheet_category" :label="__('client.services.field_category')"
                        :value="$sheet['category'] ?? ''" :options="[
                            ...array_combine($categories, $categories),
                            '' => __('client.services.uncategorized'),
                        ]" />
                </x-catalog.form-row>
                <x-catalog.form-row>
                    <x-inputsform.input span="code" name="sheet_minutes" :label="__('client.services.field_minutes')"
                        :value="$sheet['minutes']" class="font-mono" inputmode="numeric" />
                    <x-inputsform.combobox span="text" name="sheet_price_type" :label="__('client.services.field_price_type')"
                        value="fixed" :options="[
                            'fixed' => __('client.services.price_fixed'),
                            'from' => __('client.services.price_from'),
                            'free' => __('client.services.price_free'),
                            'talk' => __('client.services.price_talk'),
                        ]" />
                </x-catalog.form-row>
                <x-catalog.form-row>
                    <x-inputsform.input span="short" name="sheet_price" :label="__('client.services.field_amount')"
                        :value="$sheet['price'] !== null ? number_format($sheet['price'], 0, ',', '.') : ''" class="font-mono" inputmode="numeric" />
                    <x-inputsform.input span="short" name="sheet_deposit" :label="__('client.services.field_deposit')"
                        :hint="__('client.services.deposit_help')"
                        :value="$sheet['deposit'] !== null ? number_format($sheet['deposit'], 0, ',', '.') : ''" class="font-mono" inputmode="numeric" />
                </x-catalog.form-row>
                <x-catalog.form-row>
                    <div class="f-full">
                        <x-ui.textarea name="sheet_description" :rows="2" :label="__('client.services.field_description')"
                            :hint="__('client.services.description_help')">{{ $sheet['description'] }}</x-ui.textarea>
                    </div>
                </x-catalog.form-row>
                <x-catalog.form-row>
                    <div class="f-full">
                        <x-ui.textarea name="sheet_prep" :rows="2" :label="__('client.services.field_prep')"
                            :hint="__('client.services.prep_help')">{{ $sheet['prep'] }}</x-ui.textarea>
                    </div>
                </x-catalog.form-row>
                <x-ui.switch name="sheet_active" :label="__('client.services.offered')" :checked="$sheet['active']" />
                <div>
                    <x-ui.switch name="sheet_featured" :label="__('client.services.featured')" :checked="$sheet['featured']" />
                    <p class="mt-1 text-sm text-muted">{{ __('client.services.featured_help') }}</p>
                </div>
            </div>

            <x-slot:footer>
                <x-ui.button variant="ghost" size="sm" wire:click="closeSheet">{{ __('client.services.sheet_cancel') }}</x-ui.button>
                <span class="flex-1"></span>
                <x-ui.button variant="primary" size="sm" wire:click="closeSheet">{{ __('client.services.sheet_save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.slide-over>
    @endif
</div>
