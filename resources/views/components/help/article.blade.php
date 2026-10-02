@props(['article', 'open' => false, 'rated' => null])

{{-- One article, one task. The answer is in the first lines, so opening it is
already the whole answer; the thumbs sit at the end, where the doubt is. --}}
<div class="help-item @if ($open) is-open @endif" wire:key="help-{{ $article->id }}">
    <button
        type="button"
        class="help-head"
        wire:click="toggle({{ $article->id }})"
        data-testid="help-article-{{ $article->slug }}"
    >
        <span class="help-title">{{ $article->title }}</span>
        <x-icon name="chevron-down" :size="16" class="help-caret" />
    </button>

    @if ($open)
        <div class="help-body">
            @foreach (preg_split('/\n\s*\n/', trim($article->body)) as $paragraph)
                <p>{!! nl2br(e(trim($paragraph))) !!}</p>
            @endforeach

            <div class="help-vote">
                @if ($rated === null)
                    <span class="help-vote-ask">{{ __('help.useful') }}</span>
                    <x-ui.button variant="ghost" size="sm" icon="thumbs-up" wire:click="rate({{ $article->id }}, true)">
                        {{ __('help.yes') }}</x-ui.button>
                    <x-ui.button
                        variant="ghost"
                        size="sm"
                        icon="thumbs-down"
                        wire:click="rate({{ $article->id }}, false)"
                        data-testid="help-no-{{ $article->slug }}"
                    >
                        {{ __('help.no') }}</x-ui.button>
                @else
                    <span class="help-vote-ask">{{ $rated ? __('help.thanks') : __('help.sorry') }}</span>
                @endif
            </div>
        </div>
    @endif
</div>
