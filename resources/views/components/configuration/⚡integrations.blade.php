<?php

use App\Services\Integrations\IntegrationHealth;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Health board for everything AtendIa consumes: on, answering, or where to
 * look. The probes run AFTER the first paint (`wire:init`): eight checks with
 * timeouts must never hold the page hostage.
 */
new #[Title('Integraciones')] class extends Component {
    /** @var array<string, array{key: string, state: string, latency_ms: int|null, detail: string|null, hint: string|null}> */
    public array $statuses = [];

    public function load(): void
    {
        $this->statuses = app(IntegrationHealth::class)
            ->statuses()
            ->keyBy(fn ($status) => $status->key)
            ->map(fn ($status) => $status->toArray())
            ->all();
    }

    /** Re-probes ONE integration: the fix is checked without re-running the rest. */
    public function recheck(string $key): void
    {
        if (! in_array($key, app(IntegrationHealth::class)->keys(), true)) {
            return;
        }

        $this->statuses[$key] = app(IntegrationHealth::class)->check($key)->toArray();
    }
};
?>

<div wire:init="load">
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('integrations.title') }}</h1>
            <p class="page-head-sub">{{ __('integrations.subtitle') }}</p>
        </div>

        <x-ui.button variant="secondary" icon="refresh-cw" wire:click="load" wire:loading.attr="disabled">
            {{ __('integrations.refresh') }}
        </x-ui.button>
    </div>

    <div class="integrations-grid">
        @foreach (app(App\Services\Integrations\IntegrationHealth::class)->keys() as $key)
            @php $status = $statuses[$key] ?? null; @endphp

            <x-ui.card class="integration-card" wire:key="integration-{{ $key }}">
                <div class="integration-head">
                    <h3 class="integration-name">{{ __('integrations.names.'.$key) }}</h3>

                    @if ($status === null)
                        {{-- First paint: the probes are still travelling. --}}
                        <span class="integration-status is-wait"><span class="dot"></span>{{ __('integrations.checking') }}</span>
                    @else
                        <span class="integration-status is-{{ $status['state'] }}">
                            <span class="dot"></span>{{ __('integrations.status.'.$status['state']) }}
                        </span>
                    @endif
                </div>

                <p class="integration-desc">{{ __('integrations.descriptions.'.$key) }}</p>

                @if ($status !== null)
                    <p class="integration-detail">
                        {{ $status['detail'] }}
                        @if ($status['latency_ms'] !== null)
                            <span class="font-mono integration-latency">{{ __('integrations.latency', ['ms' => $status['latency_ms']]) }}</span>
                        @endif
                    </p>

                    @if ($status['hint'] !== null)
                        <x-ui.alert variant="warning" icon="zap">{{ $status['hint'] }}</x-ui.alert>
                    @endif

                    <div class="integration-foot">
                        <x-ui.button variant="ghost" size="sm" icon="refresh-cw" wire:click="recheck('{{ $key }}')">
                            {{ __('integrations.recheck') }}
                        </x-ui.button>
                    </div>
                @endif
            </x-ui.card>
        @endforeach
    </div>
</div>
