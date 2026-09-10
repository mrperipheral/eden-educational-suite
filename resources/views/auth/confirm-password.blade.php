<x-layouts.guest :title="__('Confirm your password')">
    <x-auth-heading
        :title="__('Confirm your password')"
        :description="__('This is a secure area. Please confirm your password before continuing.')"
    />

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <x-input
            name="password"
            type="password"
            :label="__('Password')"
            autocomplete="current-password"
            required
            autofocus
        />

        <x-button type="submit" class="w-full">{{ __('Confirm') }}</x-button>
    </form>
</x-layouts.guest>
