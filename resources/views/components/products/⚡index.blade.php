<?php

use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Tus productos" — living mock-up, zero persistence. Same Fresha/Shopify
 * split as the services screen: the LIST finds and operates (search, filter,
 * load more), the SHEET edits in a slide-over. 'review' is the import staging
 * table where rows get fixed before anything is saved.
 */
new class extends Component
{
    public string $mode = 'loaded';

    public string $search = '';

    public string $filter = 'all';

    /** Rows on screen; "load more" grows it by a page. */
    public int $visible = 8;

    /** Open sheet: null closed, -1 a new product, >= 0 the row index. */
    public ?int $editing = null;

    /**
     * Mock rows an auto-parts shop would load. `available` off means the
     * assistant answers "sin stock por ahora" instead of dropping the row;
     * `waiting` counts clients who asked to be pinged when it returns.
     *
     * @var list<array{name: string, code: ?string, price: ?int, available: bool, waiting: int}>
     */
    public array $products = [
        ['name' => 'Alternador Fiat Palio', 'code' => 'ALT-021', 'price' => 185000, 'available' => true, 'waiting' => 0],
        ['name' => 'Bujía NGK BPR6ES', 'code' => 'NGK-6ES', 'price' => 5200, 'available' => true, 'waiting' => 0],
        ['name' => 'Correa de distribución', 'code' => 'COR-114', 'price' => 24500, 'available' => false, 'waiting' => 3],
        ['name' => 'Filtro de aceite Mann', 'code' => 'FIL-098', 'price' => 8900, 'available' => true, 'waiting' => 0],
        ['name' => 'Pastillas de freno delanteras', 'code' => null, 'price' => null, 'available' => true, 'waiting' => 0],
        ['name' => 'Amortiguador delantero Corsa', 'code' => 'AMO-215', 'price' => 78000, 'available' => true, 'waiting' => 0],
        ['name' => 'Batería 12x75 Willard', 'code' => 'BAT-075', 'price' => 145000, 'available' => true, 'waiting' => 0],
        ['name' => 'Kit de embrague Gol Trend', 'code' => 'EMB-207', 'price' => 165000, 'available' => false, 'waiting' => 1],
        ['name' => 'Radiador Clio 1.2', 'code' => 'RAD-330', 'price' => 98000, 'available' => true, 'waiting' => 0],
        ['name' => 'Bomba de agua Peugeot 208', 'code' => 'BOM-118', 'price' => 52000, 'available' => true, 'waiting' => 0],
        ['name' => 'Filtro de aire K&N', 'code' => 'FIL-201', 'price' => 32000, 'available' => true, 'waiting' => 0],
        ['name' => 'Termostato Ford Ka', 'code' => 'TER-044', 'price' => 18500, 'available' => true, 'waiting' => 0],
        ['name' => 'Disco de freno ventilado', 'code' => 'DIS-092', 'price' => 41000, 'available' => true, 'waiting' => 0],
        ['name' => 'Sensor de oxígeno Bosch', 'code' => 'SEN-310', 'price' => 67000, 'available' => false, 'waiting' => 2],
        ['name' => 'Aceite sintético 5W30 x4L', 'code' => 'ACE-530', 'price' => 38000, 'available' => true, 'waiting' => 0],
        ['name' => 'Lámpara H7 Philips', 'code' => 'LAM-007', 'price' => 6500, 'available' => true, 'waiting' => 0],
        ['name' => 'Escobillas limpiaparabrisas', 'code' => 'ESC-026', 'price' => 12000, 'available' => true, 'waiting' => 0],
        ['name' => 'Bobina de encendido VW', 'code' => 'BOB-155', 'price' => 45000, 'available' => true, 'waiting' => 0],
        ['name' => 'Junta de tapa de cilindros', 'code' => 'JUN-089', 'price' => 28000, 'available' => true, 'waiting' => 0],
        ['name' => 'Rótula de suspensión', 'code' => 'ROT-063', 'price' => 21500, 'available' => true, 'waiting' => 0],
        ['name' => 'Cable de bujía Fiat Uno', 'code' => 'CAB-012', 'price' => 15000, 'available' => false, 'waiting' => 0],
        ['name' => 'Espejo retrovisor derecho', 'code' => null, 'price' => 33000, 'available' => true, 'waiting' => 0],
        ['name' => 'Bomba de nafta sumergible', 'code' => 'BOM-240', 'price' => 89000, 'available' => true, 'waiting' => 0],
        ['name' => 'Kit de distribución completo', 'code' => 'KIT-114', 'price' => 125000, 'available' => true, 'waiting' => 0],
    ];

    /**
     * Mock staging rows of the review step: `status` paints the row and its
     * message; only the clean ones enter on confirm. The price stays a raw
     * string on purpose — it arrives dirty from the spreadsheet.
     *
     * @var list<array{name: ?string, code: ?string, price: ?string, status: string}>
     */
    public array $staged = [
        ['name' => 'Amortiguador trasero', 'code' => 'AMO-330', 'price' => '61.000', 'status' => 'ok'],
        ['name' => 'Batería 12x65', 'code' => 'BAT-065', 'price' => '98.SOO', 'status' => 'error_price'],
        ['name' => 'Correa de distribución', 'code' => 'COR-114', 'price' => '26.000', 'status' => 'duplicate'],
        ['name' => null, 'code' => null, 'price' => '6.000', 'status' => 'error_name'],
        ['name' => 'Kit de embrague', 'code' => 'EMB-207', 'price' => '145.000', 'status' => 'ok'],
    ];

    /**
     * Search (name or code) and availability filter, ORIGINAL KEYS KEPT: the
     * row actions address the real index, never the position on screen.
     *
     * @return array<int, array{name: string, code: ?string, price: ?int, available: bool, waiting: int}>
     */
    #[Computed]
    public function filtered(): array
    {
        $needle = Str::ascii(mb_strtolower(trim($this->search)));

        return array_filter($this->products, function (array $product) use ($needle): bool {
            if ($this->filter === 'available' && ! $product['available']) {
                return false;
            }
            if ($this->filter === 'out' && $product['available']) {
                return false;
            }

            return $needle === ''
                || str_contains(Str::ascii(mb_strtolower($product['name'])), $needle)
                || str_contains(Str::ascii(mb_strtolower($product['code'] ?? '')), $needle);
        });
    }

    /** @return array<int, array{name: string, code: ?string, price: ?int, available: bool, waiting: int}> */
    #[Computed]
    public function rows(): array
    {
        return array_slice($this->filtered, 0, $this->visible, preserve_keys: true);
    }

    /** Rows that enter on confirm: everything not flagged as an error. */
    #[Computed]
    public function importable(): int
    {
        return count(array_filter($this->staged, fn (array $row): bool => ! str_starts_with($row['status'], 'error')));
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

    public function switchTo(string $mode): void
    {
        $this->mode = in_array($mode, ['empty', 'loaded', 'review'], true) ? $mode : 'loaded';
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('client.products.title') }}</h1>
            <p class="page-head-sub">{{ __('client.products.sub') }}</p>
        </div>
        <div class="mock-switch" role="group" aria-label="{{ __('client.products.state_label') }}">
            <button type="button" wire:click="switchTo('empty')" @class(['is-active' => $mode === 'empty'])>
                {{ __('client.products.state_empty') }}
            </button>
            <button type="button" wire:click="switchTo('loaded')" @class(['is-active' => $mode === 'loaded'])>
                {{ __('client.products.state_loaded') }}
            </button>
            <button type="button" wire:click="switchTo('review')" @class(['is-active' => $mode === 'review'])>
                {{ __('client.products.state_review') }}
            </button>
        </div>
    </div>

    @if ($mode === 'empty')
        <x-client.offer-empty
            icon="package"
            :title="__('client.products.empty_title')"
            :body="__('client.products.empty_body')"
        >
            <x-ui.button variant="primary" size="sm" icon="upload">{{ __('client.products.import_cta') }}</x-ui.button>
            <p class="text-sm text-subtle">{{ __('client.products.empty_or') }}</p>
        </x-client.offer-empty>
    @elseif ($mode === 'review')
        {{-- Import staging, the anti-frustration pattern: nothing saves until
        confirmed, broken rows get fixed HERE, clean ones never wait. --}}
        <x-ui.card>
            <div class="flex flex-wrap items-center gap-3 border-b border-[color:var(--border-subtle)] p-5 pb-4">
                <p class="font-bold text-strong">{{ __('client.products.review_summary', ['rows' => count($staged)]) }}
                    <span class="font-mono text-sm font-normal text-muted">· lista-repuestos.xlsx</span>
                </p>
                <span class="rounded-full bg-[color:var(--success-soft)] px-2 py-0.5 text-xs font-semibold text-[color:var(--success)]">{{ __('client.products.review_ok', ['count' => 3]) }}</span>
                <span class="rounded-full bg-[color:var(--danger-soft)] px-2 py-0.5 text-xs font-semibold text-[color:var(--danger)]">{{ __('client.products.review_errors', ['count' => 2]) }}</span>
                <span class="ml-auto">
                    <x-ui.button variant="ghost" size="sm">{{ __('client.products.review_only_errors') }}</x-ui.button>
                </span>
            </div>

            <ul class="divide-y divide-[color:var(--border-subtle)] px-2">
                @foreach ($staged as $row)
                    <li @class([
                        'flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg px-3 py-2.5',
                        'bg-[color:var(--danger-soft)]' => str_starts_with($row['status'], 'error'),
                        'bg-[color:var(--warning-soft)]' => $row['status'] === 'duplicate',
                    ]) wire:key="staged-{{ $loop->index }}">
                        <div class="min-w-0 flex-1">
                            <p @class(['text-sm font-semibold', 'text-strong' => $row['name'] !== null, 'text-subtle' => $row['name'] === null])>
                                {{ $row['name'] ?? '—' }}
                            </p>
                            @if ($row['status'] === 'error_price')
                                <p class="text-xs font-semibold text-[color:var(--danger)]">{{ __('client.products.review_bad_price') }}</p>
                            @elseif ($row['status'] === 'error_name')
                                <p class="text-xs font-semibold text-[color:var(--danger)]">{{ __('client.products.review_no_name', ['row' => 18]) }}</p>
                            @elseif ($row['status'] === 'duplicate')
                                <p class="text-xs font-semibold text-[color:var(--warning)]">{{ __('client.products.review_dupe') }}</p>
                            @endif
                        </div>
                        <p class="hidden font-mono text-xs text-subtle sm:block">{{ $row['code'] ?? '—' }}</p>
                        @if ($row['status'] === 'error_price')
                            <x-inputsform.input span="code" name="staged_price_{{ $loop->index }}" class="font-mono"
                                :value="$row['price']" :aria-label="__('client.products.review_fix')" />
                            <x-ui.icon-button icon="check" size="sm" variant="ghost" :label="__('client.products.review_fix')" />
                        @else
                            <p class="font-mono text-sm text-strong">{{ $row['price'] !== null ? '$ '.$row['price'] : '—' }}</p>
                            <x-ui.icon-button :icon="$row['status'] === 'error_name' ? 'x' : 'pencil'" size="sm" variant="ghost"
                                :label="__($row['status'] === 'error_name' ? 'client.products.review_drop' : 'client.products.review_edit')" />
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="flex flex-wrap items-center gap-3 border-t border-[color:var(--border-subtle)] p-5 pt-4">
                <p class="flex-1 text-sm text-muted">{{ __('client.products.review_note') }}</p>
                <x-ui.button variant="ghost" size="sm">{{ __('client.products.review_discard') }}</x-ui.button>
                <x-ui.button variant="primary" size="sm">{{ __('client.products.review_confirm', ['count' => $this->importable]) }}</x-ui.button>
            </div>
        </x-ui.card>
    @else
        <div class="flex flex-col gap-3">
        {{-- Same humanity note as the services screen, aimed at THIS screen's
        magic moment: the list the owner already keeps, loaded without typing. --}}
        <x-ui.ai-banner
            :title="__('client.products.ai_title')"
            :body="__('client.products.ai_body')"
            :action="__('client.products.ai_action')"
        />

        {{-- ONE surface: toolbar to find, list to act; the sheet slides over. --}}
        <x-ui.card class="p-5">
            {{-- A declared row: the search absorbs the slack so the toolbar
            always reaches the right edge (golden rule, formularios §5). --}}
            <x-catalog.form-row>
                <x-inputsform.input span="long" name="list_search" icon="search"
                    wire:model.live.debounce.300ms="search"
                    :aria-label="__('client.products.search_placeholder')"
                    :placeholder="__('client.products.search_placeholder')" />
                <x-inputsform.combobox span="short" name="filter" wire:model.live="filter"
                    :value="$filter" :placeholder="__('client.products.filter_label')" :options="[
                        'all' => __('client.products.filter_all'),
                        'available' => __('client.products.filter_available'),
                        'out' => __('client.products.filter_out'),
                    ]" />
                <div class="flex flex-none items-center self-center">
                    <x-ui.button variant="primary" size="sm" icon="plus" wire:click="add">{{ __('client.products.add') }}</x-ui.button>
                </div>
            </x-catalog.form-row>

            <p class="mt-3 border-b border-[color:var(--border-subtle)] pb-3 font-mono text-sm text-subtle">
                {{ trans_choice('client.products.count', count($this->filtered), ['count' => count($this->filtered)]) }}
            </p>

            @if ($this->rows === [])
                <p class="py-6 text-center text-sm text-muted">{{ __('client.products.no_results') }}</p>
            @else
                {{-- Out of stock switches OFF, never out: the assistant answers
                "sin stock por ahora" and offers to ping whoever asked. --}}
                <ul class="divide-y divide-[color:var(--border-subtle)]">
                    @foreach ($this->rows as $index => $product)
                        <li class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg px-2 py-2.5 transition hover:bg-sunken" wire:key="product-{{ $index }}">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p @class(['text-sm font-semibold', 'text-strong' => $product['available'], 'text-muted' => ! $product['available']])>
                                        <x-ui.match :text="$product['name']" :needle="$search" />
                                    </p>
                                    @if ($product['waiting'] > 0)
                                        <span class="rounded-full bg-brand-soft px-2 py-0.5 text-xs font-semibold text-[color:var(--brand-soft-text)]">
                                            {{ trans_choice('client.products.waiting', $product['waiting'], ['count' => $product['waiting']]) }}
                                        </span>
                                    @endif
                                </div>
                                @if (! $product['available'])
                                    <p class="text-xs text-muted">{{ __('client.products.out_note') }}</p>
                                @endif
                            </div>
                            <p class="hidden font-mono text-xs text-subtle sm:block">
                                @if ($product['code'] !== null)
                                    <x-ui.match :text="$product['code']" :needle="$search" />
                                @else
                                    —
                                @endif
                            </p>
                            <p class="font-mono text-sm font-bold text-strong">{{ $product['price'] !== null ? '$ '.number_format($product['price'], 0, ',', '.') : '—' }}</p>
                            <x-ui.switch name="available_{{ $index }}" :checked="$product['available']" size="sm" />
                            <x-ui.icon-button icon="pencil" size="sm" variant="ghost" wire:click="edit({{ $index }})"
                                :label="__('client.products.edit', ['name' => $product['name']])" />
                        </li>
                    @endforeach
                </ul>

                <x-ui.load-more :shown="count($this->rows)" :total="count($this->filtered)" action="loadMore" />
            @endif
        </x-ui.card>

        {{-- The import lane lives beside the list, not inside it: a spreadsheet
        of hundreds must never feel like typing them one by one. --}}
        <x-ui.card class="p-5">
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex size-11 items-center justify-center rounded-xl bg-brand-soft">
                    <x-icon name="upload" :size="20" style="color:var(--brand)" />
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="font-display text-base font-bold text-strong">{{ __('client.products.import_title') }}</h2>
                    <p class="text-sm text-muted">{{ __('client.products.import_body') }}</p>
                </div>
                <x-ui.button variant="secondary" size="sm" icon="upload">{{ __('client.products.import_cta') }}</x-ui.button>
            </div>
            <p class="mt-3 border-t border-[color:var(--border-subtle)] pt-3 font-mono text-xs text-subtle">
                {{ __('client.products.import_last', ['file' => 'repuestos-2026.xlsx', 'rows' => 152]) }}
            </p>
        </x-ui.card>
        </div>
    @endif

    {{-- The sheet slides over the list: editing never loses the place. -1 is
    a blank sheet for a new product. --}}
    @if ($editing !== null)
        @php $sheet = $products[$editing] ?? ['name' => '', 'code' => null, 'price' => null, 'available' => true, 'waiting' => 0]; @endphp
        <x-ui.slide-over
            x-on:slide-over-close="$wire.closeSheet()"
            :title="$editing >= 0 ? __('client.products.sheet_title', ['name' => $sheet['name']]) : __('client.products.sheet_new_title')"
            :subtitle="__('client.products.sheet_hint')"
        >
            <div class="flex flex-col gap-4">
                <x-catalog.form-row>
                    <x-inputsform.input span="full" name="sheet_name" :label="__('client.products.field_name')"
                        :value="$sheet['name']" />
                </x-catalog.form-row>
                <x-catalog.form-row>
                    <x-inputsform.input span="code" name="sheet_code" :label="__('client.products.field_code')"
                        :value="$sheet['code']" class="font-mono" />
                    <x-inputsform.input span="short" name="sheet_price" :label="__('client.products.field_price')"
                        :value="$sheet['price'] !== null ? number_format($sheet['price'], 0, ',', '.') : ''" class="font-mono" inputmode="numeric" />
                </x-catalog.form-row>
                <x-ui.switch name="sheet_available" :label="__('client.products.sheet_available')" :checked="$sheet['available']" />
            </div>

            <x-slot:footer>
                <x-ui.button variant="ghost" size="sm" wire:click="closeSheet">{{ __('client.products.sheet_cancel') }}</x-ui.button>
                <span class="flex-1"></span>
                <x-ui.button variant="primary" size="sm" wire:click="closeSheet">{{ __('client.products.sheet_save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.slide-over>
    @endif
</div>
