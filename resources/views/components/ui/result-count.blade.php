@props(['shown', 'total', 'noun'])

{{-- "4 of 34" only when something is held back: a count equal to the whole
says nothing the table does not already. `{noun}_of` is the "of" sentence. --}}
<span {{ $attributes->class('text-subtle font-mono text-sm') }} data-testid="result-count">
    @if ($shown < $total)
        {{ trans_choice($noun.'_of', $total, ['shown' => $shown, 'total' => $total]) }}
    @else
        {{ trans_choice($noun, $shown, ['count' => $shown]) }}
    @endif
</span>
