<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">{{ __('auth.verify.intro') }}</div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 text-sm font-medium text-green-600">{{ __('auth.verify.sent') }}</div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button> {{ __('auth.verify.resend') }} </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button
                type="submit"
                class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                {{ __('auth.verify.logout') }}
            </button>
        </form>
    </div>
</x-guest-layout>
