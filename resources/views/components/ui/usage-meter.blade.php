{{-- One meter for every usage bar (plan screen, home strip): label left,
mono figures right, jade fill that turns warning at 80% and danger past the
cap via data-state. Repeating this markup was how the copies drifted. --}}
@props([
    'label',
    'text',
    'percent' => 0,
    'state' => 'ok',
])

<div class="plan-meter" data-state="{{ $state }}">
    <div class="plan-meter-head">
        <span>{{ $label }}</span>
        <b>{{ $text }}</b>
    </div>
    <div class="plan-meter-track">
        <div class="plan-meter-fill" style="width: {{ min(100, max(0, (int) $percent)) }}%"></div>
    </div>
</div>
