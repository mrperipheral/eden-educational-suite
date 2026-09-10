<x-layouts.guest :title="__('Reset your password')">
    <x-auth-heading
        :title="__('Reset your password')"
        :description="__('Enter your email address and we will send you a reset link.')"
    />

    @if (session('status'))
        <x-alert variant="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-input name="email" type="email" :label="__('Email address')" autocomplete="username" required autofocus />

        <x-button type="submit" class="w-full">{{ __('Email password reset link') }}</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700">{{ __('Back to sign in') }}</a>
    </p>
</x-layouts.guest>
