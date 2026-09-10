<p class="mb-4 text-sm text-gray-600">
    {{ __('Once your account is deleted, all of its data is permanently removed. This cannot be undone.') }}
</p>

<form method="POST" action="{{ route('settings.profile.destroy') }}">
    @csrf
    @method('DELETE')

    <x-button type="submit" variant="danger">{{ __('Delete account') }}</x-button>
    <p class="mt-2 text-xs text-gray-500">
        {{ __('You will be asked to confirm your password.') }}
    </p>
</form>
