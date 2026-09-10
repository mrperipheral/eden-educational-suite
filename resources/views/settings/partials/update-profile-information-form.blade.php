<form method="POST" action="{{ route('settings.profile.update') }}" class="space-y-4">
    @csrf
    @method('PATCH')

    <x-input name="name" :label="__('Full name')" :value="old('name', $user->name)" autocomplete="name" required />

    <x-input
        name="email"
        type="email"
        :label="__('Email address')"
        :value="old('email', $user->email)"
        autocomplete="username"
        required
    />

    @if (! $user->hasVerifiedEmail())
        <x-alert variant="warning">
            {{ __('Your email address is not verified.') }}
            <button
                form="resend-verification"
                class="ml-1 font-medium underline hover:no-underline"
            >{{ __('Resend verification email') }}</button>
        </x-alert>
    @endif

    <div class="flex items-center gap-3">
        <x-button type="submit">{{ __('Save changes') }}</x-button>
    </div>
</form>

<form id="resend-verification" method="POST" action="{{ route('verification.send') }}" class="hidden">
    @csrf
</form>
