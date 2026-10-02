<?php

use App\Dto\AdoptionRowDto;
use App\Enums\AdoptionStep;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Where each business stalled on the way to being answered by its own
 * assistant. Nothing is recorded for this screen: the trail already exists in
 * the logins, the catalog and the conversations, and nobody read it together.
 */
new class extends Component
{
    #[Url(as: 'ver', except: 'stalled')]
    public string $filter = 'stalled';

    /** The open row, by email: the one field every account has. */
    public ?string $expanded = null;

    public function toggle(string $email): void
    {
        $this->expanded = $this->expanded === $email ? null : $email;
    }

    /** @return Collection<int, AdoptionRowDto> */
    #[Computed]
    public function accounts(): Collection
    {
        return User::adoptionRows();
    }

    /**
     * The same accounts the funnel counts, narrowed for reading. Filtering a
     * loaded collection is presentation, so it stays here.
     *
     * @return Collection<int, AdoptionRowDto>
     */
    #[Computed]
    public function rows(): Collection
    {
        if ($this->filter === 'all') {
            return $this->accounts;
        }

        return $this->accounts->filter(fn (AdoptionRowDto $row): bool => $row->isStalled)->values();
    }

    /**
     * How many accounts sit at each step. A list of rows never shows WHERE
     * the product loses people; this line does.
     *
     * @return Collection<string, int>
     */
    #[Computed]
    public function funnel(): Collection
    {
        return collect(AdoptionStep::cases())->mapWithKeys(fn (AdoptionStep $step): array => [
            $step->value => $this->accounts->where('step', $step)->count(),
        ]);
    }

    /**
     * How long each leg takes, averaged over the accounts that actually
     * walked it. Where people stall says what is broken; how long they take
     * says what is hard, and a stuck account looks the same in both.
     *
     * @return Collection<int, array{step: string, days: int, accounts: int}>
     */
    #[Computed]
    public function legs(): Collection
    {
        $steps = AdoptionStep::cases();

        return collect($steps)
            ->map(function (AdoptionStep $from, int $index) use ($steps): ?array {
                $to = $steps[$index + 1] ?? null;

                if ($to === null) {
                    return null;
                }

                $walked = $this->accounts
                    ->map(fn (AdoptionRowDto $row): ?int => $row->daysBetween($from, $to))
                    ->filter(fn (?int $days): bool => $days !== null);

                return $walked->isEmpty() ? null : [
                    'step' => $to->label(),
                    'days' => (int) round((float) $walked->avg()),
                    'accounts' => $walked->count(),
                ];
            })
            ->filter()
            ->values();
    }

    /** @return array<int, AdoptionStep> */
    public function steps(): array
    {
        return AdoptionStep::cases();
    }

    public function totalSteps(): int
    {
        return AdoptionStep::total();
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
    </x-ui.page-head>

    @if ($this->accounts->isNotEmpty())
        <x-ui.card class="p-5 mb-3">
            <p class="sup-meta">{{ __('adoption.funnel_title') }}</p>
            <div class="adp-funnel">
                @foreach ($this->funnel as $step => $total)
                    <span class="adp-step" wire:key="funnel-{{ $step }}">
                        <span class="adp-step-label">{{ __('adoption.steps.'.$step) }}</span>
                        <strong class="adp-step-total font-mono">{{ $total }}</strong>
                    </span>
                @endforeach
            </div>

            @if ($this->legs->isNotEmpty())
                {{-- Where people stall says what is broken; how long each leg
                takes says what is hard, and both read from the same dates. --}}
                <p class="sup-meta adp-legs-title">{{ __('adoption.legs_title') }}</p>
                <div class="adp-funnel">
                    @foreach ($this->legs as $leg)
                        <span class="adp-step" wire:key="leg-{{ $loop->index }}">
                            <span class="adp-step-label">{{ $leg['step'] }}</span>
                            <strong class="adp-step-total font-mono">{{ trans_choice('adoption.leg_days', $leg['days'], ['count' => $leg['days']]) }}</strong>
                        </span>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    @endif

    <x-ui.card class="p-5">
        <x-catalog.form-row>
            <x-inputsform.combobox
                span="short"
                name="filter"
                wire:model.live="filter"
                :value="$filter"
                :placeholder="__('adoption.filter')"
                :options="['stalled' => __('adoption.filter_stalled'), 'all' => __('adoption.filter_all')]"
            />
        </x-catalog.form-row>

        @forelse ($this->rows as $row)
            <div class="adp-row" wire:key="row-{{ md5($row->email) }}">
                <span class="adp-main">
                    <button type="button" class="adp-name text-left" wire:click="toggle('{{ $row->email }}')"
                        data-testid="adp-expand-{{ md5($row->email) }}">
                        {{ $row->business ?? __('adoption.no_business') }}
                    </button>
                    <span class="sup-meta">
                        <span>{{ $row->owner }}</span>
                        <span class="font-mono">{{ $row->email }}</span>
                        <span class="sup-age">{{ __('adoption.registered', ['when' => $row->registeredAt->diffForHumans()]) }}</span>
                    </span>

                    @if ($expanded === $row->email)
                        {{-- The dates behind the step, so a row that stalled
                        says WHEN it stalled without opening anything else. --}}
                        <span class="adp-detail">
                            @foreach ($this->steps() as $step)
                                <span class="adp-detail-line" wire:key="detail-{{ md5($row->email) }}-{{ $step->value }}">
                                    <span>{{ $step->label() }}</span>
                                    <span class="sup-age">
                                        {{ $row->reachedAt($step)?->translatedFormat('d/m/Y') ?? __('adoption.not_yet') }}
                                    </span>
                                </span>
                            @endforeach
                        </span>
                    @endif
                </span>

                <span class="adp-side">
                    {{-- The step reached is the whole point of the row, so it
                    reads as a tag and the finished ones wear the brand. --}}
                    @if ($row->isStalled)
                        <span class="status-tag is-neutral">{{ $row->step->label() }}</span>
                    @else
                        <span class="status-tag is-brand">{{ $row->step->label() }}</span>
                    @endif
                    <span class="sup-age">{{ __('adoption.step', ['position' => $row->step->position(), 'total' => $this->totalSteps()]) }}</span>
                </span>

                <span class="adp-side">
                    <span class="adp-idle">
                        @if ($row->daysIdle === null)
                            {{ __('adoption.never_returned') }}
                        @else
                            {{ trans_choice('adoption.idle', $row->daysIdle, ['count' => $row->daysIdle]) }}
                        @endif
                    </span>
                    {{-- A zero here is noise: the step already says it never
                    got a message. Only what happened is printed. --}}
                    <span class="sup-meta">
                        @if ($row->conversations > 0)
                            <span><strong class="font-mono">{{ $row->conversations }}</strong> {{ trans_choice('adoption.conversations', $row->conversations) }}</span>
                        @endif
                        @if ($row->tickets > 0)
                            <span><strong class="font-mono">{{ $row->tickets }}</strong> {{ trans_choice('adoption.tickets', $row->tickets) }}</span>
                        @endif
                    </span>
                </span>
            </div>
        @empty
            @if ($this->accounts->isEmpty())
                <x-ui.empty-state icon="signal" :title="__('adoption.empty_title')" :body="__('adoption.empty_body')" compact />
            @else
                <x-ui.empty-state icon="check-check" :title="__('adoption.done_title')" :body="__('adoption.done_body')" compact />
            @endif
        @endforelse
    </x-ui.card>
</div>
