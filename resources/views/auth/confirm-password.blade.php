<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">{{ __('auth.confirm.intro') }}</div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('auth.fields.password')" />

            <x-text-input
                id="password"
                class="mt-1 block w-full"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4 flex justify-end">
            <x-primary-button> {{ __('auth.confirm.submit') }} </x-primary-button>
        </div>
    </form>
</x-guest-layout>
