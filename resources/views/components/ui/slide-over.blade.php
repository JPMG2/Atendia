@props([
    'title' => null,
    'subtitle' => null,
    'stackedFooter' => false, // the footer holds a form, not a row of buttons
])

{{-- Edit panel for long lists: the list stays visible behind it. Escape, the
backdrop and the X all dispatch `slide-over-close`; the CALLER decides what
closing means (listen with x-on:slide-over-close). Closing never saves. An open
dialog takes Escape first, so it never closes the panel under it as well. --}}
<div
    {{ $attributes->merge(['class' => 'slide-over-backdrop']) }}
    x-data
    x-on:keydown.escape.window="document.body.classList.contains('has-dialog') || $dispatch('slide-over-close')"
    x-on:click.self="$dispatch('slide-over-close')"
>
    <aside class="slide-over" role="dialog" aria-modal="true" @if ($title !== null) aria-label="{{ $title }}" @endif>
        <header class="slide-over-head">
            <div class="min-w-0 flex-1">
                @if ($title !== null)
                    <h2 class="text-strong font-display text-lg font-bold">{{ $title }}</h2>
                @endif
                @if ($subtitle !== null)
                    <p class="text-muted mt-0.5 text-sm">{{ $subtitle }}</p>
                @endif
            </div>
            <x-ui.icon-button
                icon="x"
                size="sm"
                variant="ghost"
                :label="__('dialog.close')"
                x-on:click="$dispatch('slide-over-close')"
            />
        </header>

        <div class="slide-over-body">{{ $slot }}</div>

        @isset($footer)
            <footer class="slide-over-foot {{ $stackedFooter ? 'is-stacked' : '' }}">{{ $footer }}</footer>
        @endisset
    </aside>
</div>
