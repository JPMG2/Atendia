@php($replies = App\Models\Conversation::answeredThisWeekCount())

{{-- Real platform numbers or nothing: below the floor a tiny count would
un-charm, same bar as the hero's social proof. --}}
@if ($replies >= (int) config('atendia.tally_floor'))
    <section class="flex w-full justify-center pb-6">
        <p class="text-muted inline-flex items-center gap-1.5 px-6 text-center" style="font-size: var(--text-sm)">
            <x-icon name="message-circle" :size="16" style="color: var(--brand)" />
            {{ trans_choice('landing.tally.line', $replies, ['count' => number_format($replies, 0, ',', '.')]) }}
        </p>
    </section>
@endif
