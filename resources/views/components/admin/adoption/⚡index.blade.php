<?php

use App\Classes\Main\AdoptionBoard;
use App\Dto\AdoptionRowDto;
use App\Enums\AdoptionMarkKind;
use App\Enums\AdoptionSituation;
use App\Enums\AdoptionStep;
use App\Models\AdoptionMark;
use App\Models\AdoptionNudge;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Where each business stalled on the way to being answered by its own
 * assistant, and who to write to today. Nothing is recorded for this screen:
 * the trail already exists in the logins, the catalog and the conversations.
 */
new class extends Component
{
    /** The tab, in the URL: a link from the weekly mail lands on it. */
    #[Url(as: 'ver', except: 'stalled')]
    public string $view = 'stalled';

    public function show(string $view): void
    {
        $this->view = AdoptionSituation::tryFrom($view)?->value ?? AdoptionSituation::Stalled->value;
    }

    /** The same piece the app reads: funnel, tabs and notes cannot differ between the two. */
    #[Computed]
    public function board(): AdoptionBoard
    {
        return new AdoptionBoard($this->view);
    }

    /** @return Collection<int, AdoptionRowDto> */
    #[Computed]
    public function accounts(): Collection
    {
        return $this->board->accounts;
    }

    #[Computed]
    public function situation(): AdoptionSituation
    {
        return $this->board->situation;
    }

    /** @return Collection<int, AdoptionRowDto> */
    #[Computed]
    public function rows(): Collection
    {
        return $this->board->rows;
    }

    /** What the export menu carries to the report: the tab on screen. */
    #[Computed]
    public function exportParams(): array
    {
        return ['ver' => $this->situation->value];
    }

    /** @return array<string, int> How many accounts each tab holds. */
    #[Computed]
    public function counts(): array
    {
        return $this->board->counts;
    }

    /** @return Collection<int, array{step: AdoptionStep, reached: int, stopped: int|null, share: float}> */
    #[Computed]
    public function flow(): Collection
    {
        return $this->board->flow;
    }

    /** Notes that she wrote. Only an account on this screen can be noted: the email is not trusted. */
    public function markWritten(string $email): void
    {
        $row = $this->accounts->firstWhere('email', $email);

        if ($row === null) {
            return;
        }

        AdoptionMark::record($email, $row->step, AdoptionMarkKind::Written, auth()->id());
        unset($this->board);
    }

    /** @return array<string, string> The mail to open for each account, by email. */
    #[Computed]
    public function mailtos(): array
    {
        return AdoptionNudge::mailtosFor($this->accounts);
    }

    public function render(): View
    {
        // A full-page admin screen, so this title DOES reach the tab.
        return $this->view()->title(__('adoption.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('adoption.title')" :sub="__('adoption.sub')">
        <x-slot:inline>
            <span class="status-tag">{{ trans_choice('adoption.count', $this->accounts->count(), ['count' => $this->accounts->count()]) }}</span>
        </x-slot:inline>
        @if ($this->accounts->isNotEmpty())
            <x-ui.export-menu report="adopcion" :params="$this->exportParams" />
        @endif
    </x-ui.page-head>

    @if ($this->accounts->isEmpty())
        <x-ui.card class="p-5">
            <x-ui.empty-state icon="signal" :title="__('adoption.empty_title')" :body="__('adoption.empty_body')" compact />
        </x-ui.card>
    @else
        <x-ui.card class="p-5 mb-3">
            <div class="bp-card-head"><h2>{{ __('adoption.flow_title') }}</h2></div>
            <p class="bp-card-sub">{{ __('adoption.flow_hint') }}</p>

            <div class="adp-flow">
                @foreach ($this->flow as $point)
                    <div class="adp-flow-step" wire:key="flow-{{ $point['step']->value }}">
                        <div @class(['adp-bar', 'is-empty' => $point['reached'] === 0])>
                            <span class="adp-bar-fill" style="width:{{ $point['share'] }}%"></span>
                            <strong class="adp-bar-count font-mono">{{ $point['reached'] }}</strong>
                        </div>
                        <div class="adp-flow-name">{{ $point['step']->label() }}</div>
                        @if ($point['stopped'] !== null && $point['stopped'] > 0)
                            <div class="adp-flow-loss">{{ trans_choice('adoption.flow_loss', $point['stopped'], ['count' => $point['stopped']]) }}</div>
                        @else
                            <div class="adp-flow-loss is-none">{{ $point['stopped'] === null ? '' : __('adoption.flow_none') }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card class="p-5">
            <div class="bp-card-head"><h2>{{ __('adoption.list_title') }}</h2></div>
            <p class="bp-card-sub">{{ __('adoption.list_hint') }}</p>

            {{-- The tab is server state, in the URL: not x-ui.tabs, which keeps its own in the browser. --}}
            <div role="tablist" class="tabs mb-3">
                @foreach (AdoptionSituation::cases() as $case)
                    <button type="button" role="tab" wire:click="show('{{ $case->value }}')" wire:key="tab-{{ $case->value }}"
                        class="tab {{ $this->situation === $case ? 'tab-active' : '' }}"
                        aria-selected="{{ $this->situation === $case ? 'true' : 'false' }}">
                        <span>{{ $case->label() }}</span>
                        <span class="tab-badge">{{ $this->counts[$case->value] }}</span>
                    </button>
                @endforeach
            </div>

            @if ($this->rows->isEmpty())
                <x-ui.empty-state
                    icon="check-check"
                    :title="__('adoption.empty_tab.'.$this->situation->value.'.title')"
                    :body="__('adoption.empty_tab.'.$this->situation->value.'.body')"
                    compact
                />
            @else
                <div class="pay-table-wrap">
                    <table class="pay-table" data-sortable>
                        <thead>
                            <tr>
                                <th>{{ __('adoption.columns.business') }}</th>
                                <th>{{ __('adoption.columns.stage') }}</th>
                                <th>{{ __('adoption.columns.idle') }}</th>
                                <th>{{ __('adoption.columns.why') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->rows as $row)
                                @php($mailto = $this->mailtos[$row->email] ?? null)
                                <tr wire:key="row-{{ md5($row->email) }}">
                                    <td class="is-name is-key" data-label="{{ __('adoption.columns.business') }}" data-sort-value="{{ $row->business ?? '' }}">
                                        {{ $row->business ?? __('adoption.no_business') }}
                                        <span class="aiu-note">{{ $row->owner }}</span>
                                        <span class="aiu-note font-mono">{{ $row->email }}</span>
                                    </td>

                                    <td class="is-name adp-stage" data-label="{{ __('adoption.columns.stage') }}" data-sort-value="{{ $row->step->position() }}">
                                        <span @class(['status-tag', 'is-brand' => $row->situation === AdoptionSituation::Active, 'is-neutral' => $row->situation !== AdoptionSituation::Active])>{{ $row->step->label() }}</span>
                                        <small>{{ $this->board->since($row) }}</small>
                                    </td>

                                    <td class="adp-idle font-mono" data-label="{{ __('adoption.columns.idle') }}" data-sort-value="{{ $row->daysIdle ?? 99999 }}">
                                        {{ $this->board->idle($row) }}
                                    </td>

                                    {{-- The rule that put it here, in words: a row that cannot say why it is listed is noise. --}}
                                    <td class="is-name adp-why" data-label="{{ __('adoption.columns.why') }}" data-sort-value="{{ $row->daysInStep }}">
                                        {{ $this->board->why($row) }}
                                        @if ($rule = $this->board->rule($row))
                                            <em>{{ $rule }}</em>
                                        @endif
                                    </td>

                                    <td data-label="">
                                        <div class="adp-actions">
                                            {{-- A business has a file to open; an account without one has only its mail. --}}
                                            @if ($row->businessId !== null)
                                                <x-ui.button size="sm" variant="secondary" :href="route('admin.businesses', ['negocio' => $row->businessId])" wire:navigate data-row-action>
                                                    {{ __('adoption.actions.view_business') }}
                                                </x-ui.button>
                                            @endif
                                            @if ($mailto !== null && $row->situation !== AdoptionSituation::Active)
                                                {{-- The click both opens her mail program and notes that she wrote. --}}
                                                @if ($row->businessId === null)
                                                    <x-ui.button size="sm" variant="secondary" icon="mail" :href="$mailto" wire:click="markWritten({{ Illuminate\Support\Js::from($row->email) }})" data-row-action>
                                                        {{ __('adoption.actions.write') }}
                                                    </x-ui.button>
                                                @else
                                                    <x-ui.button size="sm" variant="ghost" icon="mail" :href="$mailto" wire:click="markWritten({{ Illuminate\Support\Js::from($row->email) }})">
                                                        {{ __('adoption.actions.write') }}
                                                    </x-ui.button>
                                                @endif
                                            @endif
                                        </div>
                                        {{-- So the same account is not written to twice by two people, or twice by her. --}}
                                        @if ($note = $this->board->writtenNote($row, auth()->id()))
                                            <small class="adp-written">{{ $note }}</small>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <p class="aiu-foot">{{ __('adoption.legend.'.$this->situation->value) }}</p>
        </x-ui.card>
    @endif

    <x-ui.key-hints />
</div>
