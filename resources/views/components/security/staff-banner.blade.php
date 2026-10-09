@php
    $rule = auth()->user() ? new \App\Classes\Main\StaffTwoFactor(auth()->user()) : null;
@endphp

{{-- The plazo is running: say how long is left, on every screen but the one that fixes it. --}}
@if ($rule?->applies && ! $rule->overdue && ! request()->routeIs('admin.security'))
    <x-ui.alert variant="warning" icon="lock" class="mb-3" data-testid="staff-two-factor-banner">
        {{ trans_choice('security.staff.banner', $rule->daysLeft, ['count' => $rule->daysLeft]) }}
        <a href="{{ route('admin.security') }}" wire:navigate class="row-link">{{ __('security.staff.banner_cta') }}</a>
    </x-ui.alert>
@endif
