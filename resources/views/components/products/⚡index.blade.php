<?php

use Livewire\Component;

/**
 * "Tus productos" — living mock-up, zero persistence. Two states like the
 * home: 'empty' teaches the two ways in (type them, or import the Excel the
 * business already keeps); 'loaded' shows the pills the assistant answers
 * from plus the import lane. The switch is a mock-up affordance.
 */
new class extends Component
{
    public string $mode = 'loaded';

    /**
     * Mock names an auto-parts shop would load: the universal core is the
     * NAME — the assistant only needs to answer "¿tienen X?".
     *
     * @var list<string>
     */
    public array $products = [
        'Alternador Fiat Palio',
        'Bujía NGK BPR6ES',
        'Correa de distribución',
        'Filtro de aceite Mann',
        'Pastillas de freno delanteras',
        'Amortiguador trasero',
        'Batería 12x65',
    ];

    public function switchTo(string $mode): void
    {
        $this->mode = in_array($mode, ['empty', 'loaded'], true) ? $mode : 'loaded';
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
    @else
        <div class="flex flex-col gap-4">
        <x-ui.card class="p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="font-mono text-sm text-subtle">{{ trans_choice('client.products.count', count($products), ['count' => count($products)]) }}</p>
                <x-ui.button variant="primary" size="sm" icon="plus">{{ __('client.products.add') }}</x-ui.button>
            </div>

            <div class="mt-4 flex flex-wrap items-end gap-3">
                <x-inputsform.input span="long" name="product_draft" maxlength="255"
                    :aria-label="__('client.products.add')" :placeholder="__('client.products.add_placeholder')" />
                <x-ui.button variant="secondary" size="sm">{{ __('client.products.add_short') }}</x-ui.button>
            </div>

            <div class="wizard-pills">
                @foreach ($products as $product)
                    <span class="wizard-pill" wire:key="product-{{ $loop->index }}">
                        {{ $product }}
                        <button type="button" aria-label="{{ __('client.products.remove', ['name' => $product]) }}">&times;</button>
                    </span>
                @endforeach
            </div>
        </x-ui.card>

        {{-- The import lane lives beside the list, not inside it: a spreadsheet
        of hundreds must never feel like typing them one by one. --}}
        <x-ui.card class="p-5 sm:p-6">
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
