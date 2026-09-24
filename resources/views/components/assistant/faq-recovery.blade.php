@props([
    'recovery', // array{sent: int, days: int, returned: int}
])

{{-- Customers won back: who got the answer late, after how long, and who wrote again. --}}
<span class="mt-0.5 flex items-center gap-1.5 text-xs" style="color: var(--brand)">
    <x-icon name="send" :size="14" class="flex-none" />
    <span>
        {{ trans_choice('client.assistant.recovery_sent', $recovery['sent'], ['count' => $recovery['sent']]) }} · {{ trans_choice('client.assistant.recovery_days', $recovery['days'], ['count' => $recovery['days']]) }}
        @if ($recovery['returned'] > 0)
            ·
            <b>{{ trans_choice('client.assistant.recovery_returned', $recovery['returned'], ['count' => $recovery['returned']]) }}</b>
        @endif
    </span>
</span>
