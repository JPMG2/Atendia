<x-guest-layout>
    <div class="text-center">
        <span
            class="bg-brand-soft inline-flex h-12 w-12 items-center justify-center rounded-full"
            style="color: var(--brand)"
        >
            <x-icon name="shield-check" :size="24" />
        </span>

        <h2
            class="text-strong mt-4 font-display"
            style="font-weight: 800; font-size: var(--text-2xl); letter-spacing: -0.02em"
        >
            {{ __('security.revoked.title') }}
        </h2>

        <p class="text-muted mt-2" style="font-size: var(--text-base)">{{ __('security.revoked.body') }}</p>

        <div class="mt-6 flex flex-col items-center gap-3">
            <x-ui.button variant="primary" :href="route('password.request')">
                {{ __('security.revoked.cta') }}
            </x-ui.button>
            <a
                href="{{ route('login') }}"
                class="text-brand font-semibold hover:underline"
                style="font-size: var(--text-sm)"
            >
                {{ __('security.revoked.back') }}
            </a>
        </div>
    </div>
</x-guest-layout>
