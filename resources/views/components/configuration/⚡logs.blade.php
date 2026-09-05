<?php

use App\Services\Logs\LogReader;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * System logs, newest first, built to be COPIED: each entry travels verbatim
 * (headline plus trace) so pasting it into a help conversation points at the
 * error without anyone ssh-ing into the server.
 */
new class extends Component
{
    /**
     * `#[Locked]` because the file name reaches the reader: the browser picks
     * one through {@see selectFile()}, which only accepts the known list.
     */
    #[Locked]
    public string $file = '';

    /** @var array<int, array{timestamp: string, environment: string, level: string, message: string, raw: string}> */
    public array $entries = [];

    public function mount(): void
    {
        $this->file = app(LogReader::class)->files()[0] ?? '';

        $this->load();
    }

    public function load(): void
    {
        $this->entries = $this->file === ''
            ? []
            : app(LogReader::class)->entries($this->file)->map(fn ($entry) => $entry->toArray())->all();
    }

    public function selectFile(string $file): void
    {
        if (! in_array($file, app(LogReader::class)->files(), true)) {
            return;
        }

        $this->file = $file;

        $this->load();
    }

    /** The tab title comes from translations; a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('logs.title'));
    }
};
?>

{{-- The level filter lives in Alpine: the entries are already on screen and
switching levels must not cost a request. --}}
<div x-data="{ level: 'all' }">
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('logs.title') }}</h1>
            <p class="page-head-sub">{{ __('logs.subtitle') }}</p>
        </div>

        <x-ui.button variant="secondary" icon="refresh-cw" wire:click="load" wire:loading.attr="disabled">
            {{ __('logs.refresh') }}
        </x-ui.button>
    </div>

    <div class="log-toolbar">
        <div class="log-pills" role="tablist">
            @foreach (['all', 'error', 'warning', 'info'] as $option)
                <button type="button" class="log-pill" x-bind:class="{ 'is-active': level === '{{ $option }}' }"
                    x-on:click="level = '{{ $option }}'">{{ __('logs.levels.'.$option) }}</button>
            @endforeach
        </div>

        @if (count(app(App\Services\Logs\LogReader::class)->files()) > 1)
            <div class="log-pills">
                @foreach (app(App\Services\Logs\LogReader::class)->files() as $candidate)
                    <button type="button" class="log-pill font-mono @if ($candidate === $file) is-active @endif"
                        wire:click="selectFile('{{ $candidate }}')">{{ $candidate }}</button>
                @endforeach
            </div>
        @endif

        <span class="log-count">{{ __('logs.showing', ['count' => count($entries), 'file' => $file]) }}</span>
    </div>

    @if ($entries === [])
        <x-ui.card class="log-empty">{{ __('logs.empty') }}</x-ui.card>
    @endif

    <div class="log-list">
        @foreach ($entries as $index => $entry)
            @php
                // Everything above warning is trouble; the map keeps unknown
                // levels visible as info instead of dropping them.
                $tone = match ($entry['level']) {
                    'emergency', 'alert', 'critical', 'error' => 'error',
                    'warning' => 'warning',
                    'debug' => 'debug',
                    default => 'info',
                };
            @endphp

            <x-ui.card class="log-entry" wire:key="log-{{ $file }}-{{ $index }}"
                x-data="{ open: false, copied: false }"
                x-show="level === 'all' || level === '{{ $tone === 'debug' ? 'info' : $tone }}'">
                <div class="log-head">
                    <span class="log-badge is-{{ $tone }}">{{ $entry['level'] }}</span>
                    <span class="log-time font-mono">{{ $entry['timestamp'] }}</span>
                    <p class="log-message">{{ $entry['message'] }}</p>

                    <span class="log-actions">
                        {{-- The clipboard gets the entry VERBATIM from the raw
                        block, expanded or not: what is pasted for help must
                        be exactly what the log says. --}}
                        <x-ui.icon-button size="sm" variant="ghost" :label="__('logs.copy')" data-testid="log-copy"
                            x-on:click="navigator.clipboard.writeText($refs.raw.textContent).then(() => { copied = true; setTimeout(() => copied = false, 1600); })">
                            <span x-show="! copied"><x-icon name="copy" :size="16" /></span>
                            <span x-show="copied" x-cloak style="color:var(--success)"><x-icon name="check" :size="16" /></span>
                        </x-ui.icon-button>

                        <x-ui.icon-button size="sm" variant="ghost" icon="chevron-down" :label="__('logs.expand')"
                            x-on:click="open = ! open" x-bind:class="{ 'log-chevron-open': open }" />
                    </span>
                </div>

                <pre class="log-raw font-mono" x-ref="raw" x-show="open" x-cloak>{{ $entry['raw'] }}</pre>
            </x-ui.card>
        @endforeach
    </div>
</div>
