@props([
    'topics', // list<array{topic: string, asked: int, alone: int, action: ?string}>
])

{{-- One row per topic: volume, how much the assistant solved alone and
the one move that fixes the rest. The reading goes on top. --}}
<x-ui.card class="mt-4 p-6">
    <h2 class="block-title">{{ __('statistics.topics.title') }}</h2>

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
                <div class="stats-topic" role="row">
                    <span class="stats-topic-name" role="cell">{{ $topic['topic'] }}</span>
                    <span class="stats-topic-count" role="cell">
                        {{ trans_choice('statistics.topics.asked', $topic['asked'], ['count' => $topic['asked']]) }}
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
                </div>
            @endforeach
        </div>
    @else
        <p class="stats-empty">{{ __('statistics.gathering') }}</p>
    @endif
</x-ui.card>
