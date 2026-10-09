<?php

use App\Models\AskFeedback;
use App\Models\AssistantRating;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * How the two assistants are being marked, kept apart.
 *
 * Mixing them would hide the only one that matters: the assistant that talks
 * to strangers. And every share here carries its TOTAL, because a share on
 * its own turns two clicks into "the AI is at 100%".
 */
new class extends Component
{
    /** @return Collection<int, object> */
    #[Computed]
    public function businesses(): Collection
    {
        return AssistantRating::byBusiness();
    }

    /** @return array{good: int, bad: int, total: int, share: ?float} */
    #[Computed]
    public function ownerScore(): array
    {
        return AskFeedback::score();
    }

    /** @return EloquentCollection<int, AskFeedback> */
    #[Computed]
    public function ownerRejected(): EloquentCollection
    {
        return AskFeedback::rejected();
    }

    public function render(): View
    {
        // A full-page admin screen, so this title DOES reach the tab.
        return $this->view()->title(__('ai_quality.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('ai_quality.title')" :sub="__('ai_quality.sub')">
        <span class="sup-age">{{ __('ai_quality.read_at', ['time' => now()->translatedFormat('d/m/Y H:i')]) }}</span>
    </x-ui.page-head>

    <x-ui.card class="p-5 mb-3">
        <p class="sup-meta">{{ __('ai_quality.customer.title') }}</p>

        @if ($this->businesses->isEmpty())
            {{-- Not "0%": nobody marking is not the same as the assistant
            failing, and a zero here would accuse it of something untrue. --}}
            <x-ui.empty-state icon="thumbs-up" :title="__('ai_quality.score.none')" :body="__('ai_quality.customer.empty')" compact />
        @else
            <div class="pay-table-wrap">
                <table class="pay-table" data-sortable>
                    <thead>
                        <tr>
                            <th>{{ __('ai_quality.table.business') }}</th>
                            <th>{{ __('ai_quality.table.good') }}</th>
                            <th>{{ __('ai_quality.table.bad') }}</th>
                            <th>{{ __('ai_quality.table.total') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->businesses as $row)
                            <tr wire:key="quality-{{ $row->business_id }}">
                                {{-- data-label: stacked below 991px the header row is gone,
                                and a cell without its name is a loose value. --}}
                                <td class="is-key" data-label="{{ __('ai_quality.table.business') }}">{{ $row->name }}</td>
                                <td data-label="{{ __('ai_quality.table.good') }}" class="font-mono">{{ $row->good }}</td>
                                <td data-label="{{ __('ai_quality.table.bad') }}" class="font-mono">
                                    @if ($row->bad > 0)
                                        <span class="status-tag is-danger">{{ $row->bad }}</span>
                                    @else
                                        {{ $row->bad }}
                                    @endif
                                </td>
                                <td data-label="{{ __('ai_quality.table.total') }}" class="font-mono">{{ $row->total }}</td>
                                <td>
                                    @if ($row->bad > 0)
                                        <x-ui.button
                                            variant="ghost"
                                            size="sm"
                                            :href="route('admin.incidents')"
                                            wire:navigate
                                            data-row-action
                                        >{{ __('ai_quality.open_incidents') }}</x-ui.button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card class="p-5">
        <p class="sup-meta">{{ __('ai_quality.owner.title') }}</p>

        @if ($this->ownerScore['total'] === 0)
            <x-ui.empty-state icon="bot" :title="__('ai_quality.score.none')" :body="__('ai_quality.owner.empty')" compact />
        @else
            <p class="sup-age">
                <strong class="font-mono">{{ $this->ownerScore['share'] }}%</strong>
                {{ trans_choice('ai_quality.score.over', $this->ownerScore['total'], ['total' => $this->ownerScore['total']]) }}
            </p>

            {{-- The share and its warning travel together: a number built on a
            handful of clicks is read as a verdict unless it says otherwise. --}}
            @if ($this->ownerScore['total'] < 20)
                <x-ui.alert variant="warning" icon="triangle-alert" class="mb-3">
                    {{ trans_choice('ai_quality.score.warning', $this->ownerScore['total'], ['total' => $this->ownerScore['total']]) }}
                </x-ui.alert>
            @endif

            @if ($this->ownerRejected->isNotEmpty())
                <div class="pay-table-wrap">
                    <table class="pay-table" data-sortable>
                        <thead>
                            <tr>
                                <th>{{ __('ai_quality.table.question') }}</th>
                                <th>{{ __('ai_quality.table.answer') }}</th>
                                <th>{{ __('ai_quality.table.who') }}</th>
                                <th>{{ __('ai_quality.table.when') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->ownerRejected as $mark)
                                <tr wire:key="ask-{{ $mark->id }}">
                                    <td data-label="{{ __('ai_quality.table.question') }}">{{ $mark->question }}</td>
                                    <td data-label="{{ __('ai_quality.table.answer') }}">
                                        <span class="inc-excerpt">{{ $mark->answer }}</span>
                                    </td>
                                    <td data-label="{{ __('ai_quality.table.who') }}">{{ $mark->user?->name ?? __('ai_quality.unknown') }}</td>
                                    <td data-label="{{ __('ai_quality.table.when') }}" class="font-mono">{{ $mark->created_at?->translatedFormat('d/m/Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </x-ui.card>
</div>
