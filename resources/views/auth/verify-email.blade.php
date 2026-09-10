<x-layouts.guest :title="__('Verify your email')">
    <x-auth-heading
        :title="__('Verify your email address')"
        :description="__('We sent a verification link to your email. Click it to activate your account.')"
    />

    @if (session('status') === 'verification-link-sent' || session('status'))
        <x-alert variant="success" class="mb-4">
            {{ session('status') === 'verification-link-sent'
                ? __('A new verification link has been sent to your email address.')
                : session('status') }}
        </x-alert>
    @endif

    <div class="flex flex-col gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button type="submit" class="w-full">{{ __('Resend verification email') }}</x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button type="submit" variant="ghost" class="w-full">{{ __('Sign out') }}</x-button>
        </form>
    </div>
</x-layouts.guest>
