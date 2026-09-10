<x-layouts.guest :title="__('Choose a new password')">
    <x-auth-heading :title="__('Choose a new password')" />

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <x-input
            name="email"
            type="email"
            :label="__('Email address')"
            :value="old('email', $email)"
            autocomplete="username"
            required
            readonly
        />

        <x-input
            name="password"
            type="password"
            :label="__('New password')"
            autocomplete="new-password"
            required
            autofocus
            :hint="__('At least 8 characters, including a letter and a number.')"
        />

        <x-input
            name="password_confirmation"
            type="password"
            :label="__('Confirm new password')"
            autocomplete="new-password"
            required
        />

        <x-button type="submit" class="w-full">{{ __('Reset password') }}</x-button>
    </form>
</x-layouts.guest>
