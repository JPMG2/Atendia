<?php

use Livewire\Component;

/**
 * Client home — living mock-up, blessed 2026-09-06. Two states and zero
 * persistence: 'new' is the Shopify-style setup guide (the checklist IS the
 * dashboard, KPIs would only depress at zero), 'active' is the day-to-day
 * with real numbers. The switch is a mock-up affordance standing in for the
 * demo client until the real wiring lands.
 */
new class extends Component
{
    public string $mode = 'new';

    /**
     * The account step arrives done on purpose: endowed progress makes
     * people finish what did not start from zero.
     *
     * @var array<string, bool>
     */
    public array $steps = [
        'account' => true,
        'business' => false,
        'catalog' => false,
        'try' => false,
        'whatsapp' => false,
    ];

    public function switchTo(string $mode): void
    {
        $this->mode = in_array($mode, ['new', 'active'], true) ? $mode : 'new';
    }
};
?>

<div>
    @php
        $done = count(array_filter($steps));
        $total = count($steps);
        $percent = (int) round($done / max(1, $total) * 100);
    @endphp

    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('client.home.greeting', ['name' => auth()->user()?->name]) }}</h1>
            <p class="page-head-sub">{{ $mode === 'new' ? __('client.home.sub_new') : __('client.home.sub_active') }}</p>
        </div>

        <div class="mock-switch" role="group" aria-label="{{ __('client.home.mock_label') }}">
            <button type="button" wire:click="switchTo('new')" @class(['is-active' => $mode === 'new'])>
                {{ __('client.home.state_new') }}
            </button>
            <button type="button" wire:click="switchTo('active')" @class(['is-active' => $mode === 'active'])>
                {{ __('client.home.state_active') }}
            </button>
        </div>
    </div>

    @if ($mode === 'new')
        <x-ui.card class="setup-card">
            <div class="setup-head">
                <div>
                    <h2>{{ __('client.setup.title', ['percent' => $percent]) }}</h2>
                    <p>{{ __('client.setup.sub') }}</p>
                </div>
                <span class="setup-count font-mono">{{ __('client.setup.progress', ['done' => $done, 'total' => $total]) }}</span>
            </div>

            <div class="setup-bar" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                <i style="width: {{ $percent }}%"></i>
            </div>

            <div class="setup-list">
            @foreach ($steps as $key => $completed)
                <div wire:key="step-{{ $key }}" @class(['setup-step', 'is-done' => $completed, 'is-hero' => $key === 'whatsapp'])>
                    <span class="setup-tick">
                        @if ($completed)
                            <x-icon name="check" :size="14" />
                        @endif
                    </span>
                    <div class="setup-copy">
                        <b>{{ __('client.setup.steps.'.$key.'.label') }}</b>
                        <span>{{ __('client.setup.steps.'.$key.'.hint') }}</span>
                    </div>
                    @unless ($completed)
                        <x-ui.button :variant="$key === 'whatsapp' ? 'primary' : 'secondary'" size="sm">
                            {{ __('client.setup.steps.'.$key.'.cta') }}
                        </x-ui.button>
                    @endunless
                </div>
            @endforeach
            </div>
        </x-ui.card>

        {{-- Empty widgets teach what fills them: a preview of the populated
        state beats a naked zero (NN/g empty-state guidance). --}}
        <div class="preview-grid">
            <x-ui.card class="preview-card">
                <span class="preview-icon"><x-icon name="message-circle" :size="20" /></span>
                <h3>{{ __('client.previews.conversations_title') }}</h3>
                <p>{{ __('client.previews.conversations_text') }}</p>
                <div class="preview-skeleton" aria-hidden="true"><i></i><i></i><i></i></div>
            </x-ui.card>
            <x-ui.card class="preview-card">
                <span class="preview-icon"><x-icon name="bar-chart-3" :size="20" /></span>
                <h3>{{ __('client.previews.metrics_title') }}</h3>
                <p>{{ __('client.previews.metrics_text') }}</p>
                <div class="preview-skeleton" aria-hidden="true"><i></i><i></i><i></i></div>
            </x-ui.card>
        </div>
    @else
        <div class="stat-grid">
            <x-ui.stat-card :label="__('client.kpis.conversations')" value="48" delta="+12%" trend="up" icon="message-circle" tint="brand" />
            <x-ui.stat-card :label="__('client.kpis.handled')" value="41" delta="+9%" trend="up" icon="bot" tint="info" />
            <x-ui.stat-card :label="__('client.kpis.handoffs')" value="7" delta="+2" trend="flat" icon="users" tint="warning" />
            <x-ui.stat-card :label="__('client.kpis.response')" value="2s" delta="estable" trend="flat" icon="zap" tint="accent" />
        </div>

        <x-ui.card class="recent-card">
            <div class="recent-head">
                <h3>{{ __('client.recent.title') }}</h3>
                <x-ui.button variant="ghost" size="sm">{{ __('client.recent.view_all') }}</x-ui.button>
            </div>
            @foreach (__('client.recent.samples') as $index => $sample)
                <div wire:key="recent-{{ $index }}" class="recent-row">
                    <x-ui.avatar :name="$sample['name']" size="sm" />
                    <div class="recent-copy">
                        <b>{{ $sample['name'] }}</b>
                        <span>{{ $sample['text'] }}</span>
                    </div>
                    <span class="recent-time font-mono">{{ $sample['time'] }}</span>
                </div>
            @endforeach
        </x-ui.card>
    @endif
</div>
