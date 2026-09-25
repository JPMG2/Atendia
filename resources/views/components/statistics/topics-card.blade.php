@props([
    'topics', // list<array{topic: string, asked: int, alone: int, delta: ?int, samples: list<array{question: string, resolved_by: string}>, action: ?string}>
])

{{-- One row per topic: volume, how much the assistant solved alone and
the one move that fixes the rest. The reading goes on top. --}}
<x-ui.card class="mt-4 p-6">
    <div class="stats-card-head">
        <h2 class="block-title">{{ __('statistics.topics.title') }}</h2>
        <x-statistics.ask-button :question="__('ask.chart.topics')" />
    </div>

    @if ($topics !== [])
        <p class="stats-insight">
            {{ __('statistics.topics.insight', ['topic' => $topics[0]['topic'], 'share' => $topics[0]['alone']]) }}
        </p>

        <div class="stats-topics" role="table" aria-label="{{ __('statistics.topics.title') }}">
            <div class="stats-topic stats-topic-head" role="row">
                <span role="columnheader">{{ __('statistics.topics.col_topic') }}</span>
                <span role="columnheader" class="text-right">{{ __('statistics.topics.col_asked') }}</span>
                <span role="columnheader">{{ __('statistics.topics.col_alone') }}</span>
                <span role="columnheader"></span>
            </div>

            @foreach ($topics as $topic)
                <div class="stats-topic" role="row" x-data="{ open: false }">
                    <span class="stats-topic-name" role="cell">
                        {{-- The row unfolds into what customers actually wrote, the Intercom Topics habit. --}}
                        <button
                            type="button"
                            class="stats-topic-toggle"
                            x-on:click="open = ! open"
                            x-bind:aria-expanded="open"
                        >
                            <x-icon
                                name="chevron-right"
                                :size="14"
                                x-bind:class="open && 'rotate-90'"
                                class="transition-transform"
                            />
                            {{ $topic['topic'] }}
                        </button>
                    </span>
                    <span class="stats-topic-count" role="cell">
                        {{ trans_choice('statistics.topics.asked', $topic['asked'], ['count' => $topic['asked']]) }}
                        @if ($topic['delta'] !== null && $topic['delta'] !== 0)
                            <span class="stats-topic-delta" data-trend="{{ $topic['delta'] > 0 ? 'up' : 'down' }}">
                                {{ $topic['delta'] > 0 ? '↑' : '↓' }} {{ abs($topic['delta']) }}%
                            </span>
                        @endif
                    </span>
                    <span class="stats-topic-alone" role="cell">
                        <span class="plan-meter-track"
                            ><span class="plan-meter-fill block" style="width: {{ $topic['alone'] }}%"></span
                        ></span>
                        <b>{{ $topic['alone'] }}%</b>
                    </span>
                    <span class="stats-topic-action" role="cell">
                        @if ($topic['action'] === 'teach')
                            <x-ui.button
                                variant="secondary"
                                size="sm"
                                icon="sparkles"
                                :href="route('assistant')"
                                wire:navigate
                            >
                                {{ __('statistics.topics.teach') }}
                            </x-ui.button>
                        @elseif ($topic['action'] === 'catalog')
                            <x-ui.button
                                variant="secondary"
                                size="sm"
                                icon="package"
                                :href="route('my-services')"
                                wire:navigate
                            >
                                {{ __('statistics.topics.add_catalog') }}
                            </x-ui.button>
                        @endif
                    </span>
                    <ul class="stats-topic-samples" x-show="open" x-cloak>
                        @foreach ($topic['samples'] as $sample)
                            <li>
                                <span>{{ $sample['question'] }}</span>
                                <span
                                    class="stats-topic-by"
                                    data-by="{{ $sample['resolved_by'] }}"
                                >{{ __('statistics.topics.by.'.$sample['resolved_by']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @else
        <p class="stats-empty">{{ __('statistics.gathering') }}</p>
    @endif
</x-ui.card>
