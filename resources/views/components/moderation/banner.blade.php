{{-- The suspended business reads why its assistant went quiet on every screen,
in the billing pause's own chrome: one visual grammar for "your assistant stopped". --}}
@if (auth()->user()?->business?->isSuspended())
    <div class="pay-banner is-danger" role="alert">
        <x-icon name="triangle-alert" :size="20" />
        <div class="mod-banner-body">
            <p class="pay-banner-text">
                <b>{{ __('moderation.banner.title') }}</b>
                {{ __('moderation.banner.body') }}
            </p>
            <livewire:moderation.appeal />
        </div>
    </div>
@endif
