<form method="POST" action="{{ route('settings.password.update') }}" class="space-y-4">
    @csrf
    @method('PUT')

    <x-input
        name="current_password"
        type="password"
        :label="__('Current password')"
        autocomplete="current-password"
        required
    />

    <x-input
        name="password"
        type="password"
        :label="__('New password')"
        autocomplete="new-password"
        required
        :hint="__('At least 8 characters, including a letter and a number.')"
    />

    <x-input
        name="password_confirmation"
        type="password"
        :label="__('Confirm new password')"
        autocomplete="new-password"
        required
    />

    <x-button type="submit">{{ __('Change password') }}</x-button>
</form>
