<?php

use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Tus productos" — living mock-up, zero persistence. Three states like the
 * home: 'empty' teaches the two ways in (type them, or import the Excel the
 * business already keeps); 'loaded' is the inventory the assistant answers
 * from; 'review' is the import staging table where rows get fixed before
 * anything is saved. The switch is a mock-up affordance.
 */
new class extends Component
{
    public string $mode = 'loaded';

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

    /** Rows that enter on confirm: everything not flagged as an error. */
    #[Computed]
    public function importable(): int
    {
        return count(array_filter($this->staged, fn (array $row): bool => ! str_starts_with($row['status'], 'error')));
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

        <x-ui.card class="p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="font-mono text-sm text-subtle">{{ trans_choice('client.products.count', count($products), ['count' => count($products)]) }}</p>
                <x-ui.button variant="primary" size="sm" icon="plus">{{ __('client.products.add') }}</x-ui.button>
            </div>

            <div class="mt-3 flex flex-wrap items-end gap-3">
                <x-inputsform.input span="long" name="product_draft" maxlength="255"
                    :aria-label="__('client.products.add')" :placeholder="__('client.products.add_placeholder')" />
                <x-ui.button variant="secondary" size="sm">{{ __('client.products.add_short') }}</x-ui.button>
            </div>

            {{-- Out of stock switches OFF, never out: the assistant answers
            "sin stock por ahora" and offers to ping whoever asked. --}}
            <ul class="mt-4 divide-y divide-[color:var(--border-subtle)]">
                @foreach ($products as $product)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg px-2 py-2.5 transition hover:bg-sunken" wire:key="product-{{ $loop->index }}">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p @class(['text-sm font-semibold', 'text-strong' => $product['available'], 'text-muted' => ! $product['available']])>
                                    {{ $product['name'] }}
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
                        <p class="hidden font-mono text-xs text-subtle sm:block">{{ $product['code'] ?? '—' }}</p>
                        <p class="font-mono text-sm font-bold text-strong">{{ $product['price'] !== null ? '$ '.number_format($product['price'], 0, ',', '.') : '—' }}</p>
                        <x-ui.switch name="available_{{ $loop->index }}" :checked="$product['available']" size="sm" />
                        <x-ui.icon-button icon="pencil" size="sm" variant="ghost"
                            :label="__('client.products.edit', ['name' => $product['name']])" />
                    </li>
                @endforeach
            </ul>
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
</div>
