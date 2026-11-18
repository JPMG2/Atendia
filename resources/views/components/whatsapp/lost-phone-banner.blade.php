{{-- Every client screen says the number is paused, in the same chrome as the
other "your assistant stopped" banners; the way back lives on the WhatsApp screen. --}}
@if (auth()->user()?->business?->isPausedForLostPhone())
    <div class="pay-banner is-danger" role="alert" data-testid="lost-phone-banner">
        <x-icon name="shield-alert" :size="20" />
        <p class="pay-banner-text">
            <b>{{ __('whatsapp.lost_phone.banner_title') }}</b>
            {{ __('whatsapp.lost_phone.banner_body') }}
        </p>
        @unless (request()->routeIs('whatsapp'))
            @can('manage-business')
                <x-ui.button variant="primary" size="sm" :href="route('whatsapp')" wire:navigate>
                    {{ __('whatsapp.lost_phone.banner_cta') }}
                </x-ui.button>
            @endcan
        @endunless
    </div>
@endif
