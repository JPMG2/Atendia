@php($brand = \App\Models\Company::brand())

<x-guest-layout>
    <x-slot:title>{{ __('releases.title') }} · {{ $brand }}</x-slot:title>

    {{-- Opening this page is what turns off the «Nuevo» beside «Novedades» in the panel menu. --}}
    <div
        class="flex flex-col gap-6"
        data-testid="releases"
        x-data
        x-init="(() => { try { localStorage.setItem('{{ \App\Classes\Main\Releases::SEEN_KEY }}', @js($releases->current)) } catch (e) {} })()"
    >
        <header class="flex flex-col gap-2">
            <p class="eyebrow">{{ $brand }}</p>
            <h1
                class="text-strong font-display"
                style="font-weight: 800; font-size: var(--text-3xl); line-height: 1.15"
            >
                {{ __('releases.heading', ['brand' => $brand]) }}
            </h1>
            <p class="text-body">{{ __('releases.lead', ['brand' => $brand]) }}</p>
        </header>

        @foreach ($releases->entries as $release)
            <section class="bd-subtle flex flex-col gap-3 border-t pt-5">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-strong font-display" style="font-weight: 700; font-size: var(--text-lg)">
                        {{ __('releases.version', ['version' => $release['version']]) }}
                    </h2>
                    @if ($loop->first)
                        <span class="status-tag is-brand"><span class="dot"></span>{{ __('releases.current') }}</span>
                    @endif
                    <span class="status-tag">{{ $release['kind_label'] }}</span>
                    <span class="text-muted font-mono text-sm">{{ $release['date']->format('d/m/Y') }}</span>
                </div>
                <p class="text-body font-semibold">{{ $release['title'] }}</p>
                <ul class="text-body flex list-disc flex-col gap-2 pl-5">
                    @foreach ($release['items'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</x-guest-layout>
