@props([
    'row', // a PaymentsDesk row: amount, amount_ves, rate_note
])

{{-- Inside an x-data with `ves`: dollars unless the viewer asked for bolivars. --}}
<span x-show="! ves">{{ $row['amount'] }}</span>
<span x-show="ves" x-cloak>
    {{ $row['amount_ves'] ?? '—' }}<small class="money-note">{{ $row['rate_note'] }}</small>
</span>
