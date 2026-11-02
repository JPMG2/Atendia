<?php

use App\Classes\Main\ExchangeRateDesk;
use App\Classes\Main\PlatformBoard;
use App\Livewire\Forms\Admin\ExchangeRateForm;
use App\Models\ExchangeRate;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The bolivar rate every payment is charged with: which one charges, where it
 * came from, the parallel beside it as a reference, and the manual entry for
 * the day no source answers. The data comes from `ExchangeRateDesk`.
 */
new class extends Component
{
    use HasNotifications;

    public ExchangeRateForm $form;

    public function mount(): void
    {
        $this->form->setup();
    }

    #[Computed]
    public function desk(): ExchangeRateDesk
    {
        return new ExchangeRateDesk;
    }

    /**
     * Asked before saving: a jump beyond her range needs a yes on screen first.
     *
     * @return array{percent: string, rate: string}|null
     */
    public function jumpBeyondRange(): ?array
    {
        return $this->form->jumpBeyondRange();
    }

    public function save(): void
    {
        $this->dispatchNotification($this->form->save());
        unset($this->desk);
    }

    /** The value she saw travels with the click: one rewritten since the render is not hers to approve. */
    public function confirmWaiting(int $id, string $shown): void
    {
        $this->dispatchNotification($this->form->approveWaiting($id, $shown));
        unset($this->desk);
    }

    public function render(): View
    {
        return $this->view()->title(__('exchange_rate.title'));
    }
};
?>

<div x-data="exchangeRate">
    <x-ui.page-head :title="__('exchange_rate.title')" :sub="$this->desk->window" />

    @island(name: 'rate')
    <div wire:poll.{{ PlatformBoard::POLL_SECONDS }}s.visible class="flex flex-col gap-4">
        @php
            $desk = $this->desk;
            $charging = $desk->charging;
            $state = $desk->state;
            $plan = $desk->featuredPlan;
        @endphp

        <span class="text-subtle font-mono text-sm">{{ $desk->checkedAt }}</span>

        @if ($state === 'fallback')
            <x-ui.alert variant="warning" icon="triangle-alert" :title="__('exchange_rate.alerts.fallback_title')">
                {{ __('exchange_rate.alerts.fallback_body', ['source' => $charging->source->label(), 'date' => $charging->rate_date->format('d/m/Y')]) }}
            </x-ui.alert>
        @elseif ($state === 'manual')
            <x-ui.alert variant="info" icon="info" :title="__('exchange_rate.alerts.manual_title')">
                {{ __('exchange_rate.alerts.manual_body', ['date' => $charging->rate_date->format('d/m/Y')]) }}
            </x-ui.alert>
        @elseif ($state === 'sources_down')
            <x-ui.alert variant="warning" icon="triangle-alert" :title="__('exchange_rate.alerts.sources_title')">
                {{ __('exchange_rate.alerts.sources_body', ['date' => $charging?->rate_date->format('d/m/Y')]) }}
            </x-ui.alert>
        @elseif ($state === 'stale')
            <x-ui.alert variant="danger" icon="circle-alert" :title="__('exchange_rate.alerts.down_title')">
                {{ __($desk->waiting !== null ? 'exchange_rate.alerts.down_waiting_body' : 'exchange_rate.alerts.down_body', ['date' => $charging?->rate_date->format('d/m/Y')]) }}
            </x-ui.alert>
        @elseif ($state === 'none')
            <x-ui.alert variant="info" icon="info" :title="__('exchange_rate.alerts.none_title')">
                {{ __('exchange_rate.alerts.none_body') }}
            </x-ui.alert>
        @endif

        @if ($desk->waiting !== null)
            <x-ui.alert variant="warning" icon="triangle-alert" :title="__('exchange_rate.waiting.title')">
                <p>
                    {{ __('exchange_rate.waiting.body', [
                        'date' => $desk->waiting->rate_date->format('d/m/Y'),
                        'rate' => ExchangeRate::formatRate($desk->waiting->official),
                        'source' => $desk->waiting->source->label(),
                        'percent' => $desk->waitingJump ?? '—',
                    ]) }}
                </p>
                @can(ExchangeRateForm::PERMISSION)
                    <x-ui.button
                        class="mt-2"
                        variant="primary"
                        size="sm"
                        x-on:click="confirmWaiting({{ $desk->waiting->getKey() }}, {{ Illuminate\Support\Js::from(ExchangeRate::formatRate($desk->waiting->official)) }})"
                    >{{ __('exchange_rate.waiting.confirm') }}</x-ui.button>
                @endcan
            </x-ui.alert>
        @endif

        <div class="stat-grid stat-grid-fill">
            <x-ui.stat-card
                :label="__('exchange_rate.tiles.charging')"
                :value="$charging ? ExchangeRate::formatRate($charging->official) : '—'"
                icon="landmark"
                :tint="match ($state) { 'stale' => 'danger', 'fallback', 'sources_down' => 'warning', 'manual' => 'info', default => 'brand' }"
            >
                @if ($charging === null)
                    {{ __('exchange_rate.tiles.none') }}
                @elseif ($desk->isStale)
                    {{ __('exchange_rate.tiles.stale', ['date' => $charging->rate_date->format('d/m')]) }}
                @else
                    {{ __('exchange_rate.tiles.unit') }} · {{ __('exchange_rate.tiles.source', ['source' => $charging->source->label()]) }} ·
                    {{ __('exchange_rate.tiles.value_date', ['date' => $charging->rate_date->format('d/m')]) }}
                @endif
                @if ($desk->trend['official'] !== null)
                    <span class="rate-trend">
                        <x-ui.sparkline :values="$desk->trend['official']['values']" baseline="min" :width="120" :height="24" :label="__('exchange_rate.trend.official')" />
                        <span class="font-mono">{{ $desk->trend['official']['line'] }}</span>
                    </span>
                @endif
            </x-ui.stat-card>

            <x-ui.stat-card
                :label="__('exchange_rate.tiles.parallel')"
                :value="$charging?->parallel !== null ? ExchangeRate::formatRate($charging->parallel) : '—'"
                icon="info"
                tint="info"
            >
                @if ($charging?->parallel !== null)
                    {{ __('exchange_rate.tiles.parallel_note') }} ·
                    {{ __('exchange_rate.tiles.parallel_gap', ['percent' => number_format((float) $charging->parallelGap(), 1, ',', '.')]) }}
                @else
                    {{ __('exchange_rate.tiles.parallel_missing') }}
                @endif
                @if ($desk->trend['parallel'] !== null)
                    <span class="rate-trend">
                        <x-ui.sparkline :values="$desk->trend['parallel']['values']" baseline="min" :width="120" :height="24" :label="__('exchange_rate.trend.parallel')" />
                        <span class="font-mono">{{ $desk->trend['parallel']['line'] }}</span>
                    </span>
                @endif
            </x-ui.stat-card>

            @if ($plan !== null)
                <x-ui.stat-card
                    :label="__('exchange_rate.tiles.plan', ['plan' => $plan['name']])"
                    :value="$charging ? ExchangeRate::formatBolivares($charging->toBolivares($plan['dollars'])) : '—'"
                    icon="receipt"
                >{{ __('exchange_rate.tiles.plan_line', ['dollars' => $plan['dollars']]) }}</x-ui.stat-card>
            @endif
        </div>

        <x-ui.card class="p-5">
            <div class="bp-card-head"><h2>{{ __('exchange_rate.chain.title') }}</h2></div>
            <p class="bp-card-sub">{{ __('exchange_rate.chain.sub') }}</p>
            @php
                // "In use" follows the rate that really charges, not what the last look did.
                $checked = ($desk->lastCheck['at'] ?? null) !== null;
                $source = $charging?->source;
                $bcvTag = ! $checked ? ['neutral', 'not_checked'] : ($desk->bcvFailed ? ['danger', 'failed'] : ['brand', 'answered']);
                $apiTag = match (true) {
                    $source === App\Enums\ExchangeRateSource::DolarApi && ! $desk->isStale => ['brand', 'in_use'],
                    ! $checked => ['neutral', 'not_checked'],
                    $desk->apiFailed => ['danger', 'failed'],
                    default => ['brand', 'answered'],
                };
                $manualTag = match (true) {
                    $state === 'stale' || $state === 'none' => ['warning', 'waiting_you'],
                    $source === App\Enums\ExchangeRateSource::Manual => ['brand', 'in_use'],
                    $state === 'sources_down' => ['neutral', 'standby'],
                    default => ['neutral', 'not_needed'],
                };
                $chain = [
                    ['1', 'bcv', 'bcv_note', $bcvTag, $desk->lastCheck['bcv'] ?? null],
                    ['2', 'api', 'api_note', $apiTag, $desk->lastCheck['dolarapi'] ?? null],
                    ['3', 'manual', 'manual_note', $manualTag, null],
                    ['·', 'parallel', 'parallel_note', ['neutral', 'reference'], null],
                ];
            @endphp
            {{-- A list, not a table: on a phone a stacked table squeezes each name
            into a column one word wide. --}}
            <ol class="rate-chain">
                @foreach ($chain as [$step, $name, $note, $tag, $error])
                    <li wire:key="chain-{{ $name }}" class="rate-chain-row">
                        <span class="rate-chain-step font-mono">{{ $step }}</span>
                        <div class="min-w-0 flex-1">
                            <span class="text-strong font-semibold">{{ __('exchange_rate.chain.'.$name) }}</span>
                            <span class="text-muted block text-sm">{{ __('exchange_rate.chain.'.$note) }}</span>
                            @if ($error)
                                <span class="text-subtle block break-words font-mono text-xs">{{ $error }}</span>
                            @endif
                        </div>
                        <span @class([
                            'status-tag',
                            'is-brand' => $tag[0] === 'brand',
                            'is-danger' => $tag[0] === 'danger',
                            'is-warning' => $tag[0] === 'warning',
                            'is-neutral' => $tag[0] === 'neutral',
                        ])>{{ __('exchange_rate.chain.'.$tag[1]) }}</span>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>

        <x-ui.card class="p-5">
            <div class="bp-card-head"><h2>{{ __('exchange_rate.history.title') }}</h2></div>
            <p class="bp-card-sub">{{ __('exchange_rate.history.sub') }}</p>
            @if ($desk->history->isEmpty())
                <p class="text-muted text-sm">{{ __('exchange_rate.history.empty') }}</p>
            @else
                <div class="pay-table-wrap">
                    <table class="pay-table">
                        <thead>
                            <tr>
                                <th>{{ __('exchange_rate.history.date') }}</th>
                                <th>{{ __('exchange_rate.history.official') }}</th>
                                <th>{{ __('exchange_rate.history.source') }}</th>
                                <th>{{ __('exchange_rate.history.parallel') }}</th>
                                <th>{{ __('exchange_rate.history.saved') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($desk->history as $row)
                                <tr wire:key="rate-{{ $row->id }}">
                                    <td class="is-key font-mono" data-label="{{ __('exchange_rate.history.date') }}">
                                        {{ $row->rate_date->translatedFormat('D d/m/Y') }}</td>
                                    <td class="font-mono" data-label="{{ __('exchange_rate.history.official') }}">
                                        {{ ExchangeRate::formatRate($row->official) }}
                                        @if ($row->confirmed_at === null)
                                            <span class="status-tag is-warning">{{ __('exchange_rate.history.waiting') }}</span>
                                        @endif
                                    </td>
                                    <td data-label="{{ __('exchange_rate.history.source') }}">
                                        {{ $row->source === App\Enums\ExchangeRateSource::Manual && $row->enteredBy
                                            ? __('exchange_rate.history.by', ['name' => $row->enteredBy->name])
                                            : $row->source->label() }}
                                    </td>
                                    <td class="font-mono" data-label="{{ __('exchange_rate.history.parallel') }}">
                                        {{ ExchangeRate::formatRate($row->parallel) }}</td>
                                    <td class="font-mono" data-label="{{ __('exchange_rate.history.saved') }}">
                                        {{ $row->updated_at->inBusinessTime()->format('d/m H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>
    @endisland

    <x-ui.card class="mt-4 p-5">
        <div class="bp-card-head"><h2>{{ __('exchange_rate.manual.title') }}</h2></div>
        <p class="bp-card-sub">{{ __('exchange_rate.manual.sub') }}</p>
        @can(ExchangeRateForm::PERMISSION)
            <form x-on:submit.prevent="submitManual" class="flex flex-col gap-3">
                <x-catalog.form-row>
                    <x-inputsform.input
                        span="short"
                        name="rate"
                        class="font-mono"
                        inputmode="decimal"
                        :label="__('exchange_rate.manual.rate')"
                        wire:model="form.rate"
                    />
                    <x-inputsform.datepicker
                        span="text"
                        name="rate_date"
                        :label="__('exchange_rate.manual.date')"
                        :value="$form->rate_date"
                        wire:model="form.rate_date"
                    />
                </x-catalog.form-row>
                <p class="text-muted text-sm">
                    {{ __('exchange_rate.manual.hint', ['percent' => config('atendia.exchange_rate.max_jump_percent')]) }}</p>
                <div>
                    <x-ui.button type="submit" variant="primary" size="sm" wire:loading.attr="disabled">
                        {{ __('exchange_rate.manual.submit') }}</x-ui.button>
                </div>
            </form>
        @else
            <p class="text-muted text-sm">{{ __('exchange_rate.manual.no_permission') }}</p>
        @endcan
    </x-ui.card>

    <x-ui.key-hints />
</div>

@script
    <script>
        // A rate charges every bolivar payment: a big jump and a confirmation each get one question first.
        Alpine.data('exchangeRate', () => ({
            async submitManual() {
                const jump = await this.$wire.jumpBeyondRange();

                if (
                    jump !== null &&
                    !(await dialog.confirm({
                        title: @js(__('exchange_rate.manual.jump_title')),
                        message: @js(__('exchange_rate.manual.jump_message'))
                            .replace(':rate', jump.rate)
                            .replace(':percent', jump.percent),
                        accept: @js(__('exchange_rate.manual.jump_accept')),
                        type: 'warning',
                    }))
                ) {
                    return;
                }

                await this.$wire.save();
            },

            async confirmWaiting(id, rate) {
                if (
                    !(await dialog.confirm({
                        title: @js(__('exchange_rate.waiting.confirm_title')),
                        message: @js(__('exchange_rate.waiting.confirm_message')).replace(':rate', rate),
                        accept: @js(__('exchange_rate.waiting.confirm')),
                        type: 'warning',
                    }))
                ) {
                    return;
                }

                await this.$wire.confirmWaiting(id, rate);
            },
        }));
    </script>
@endscript
