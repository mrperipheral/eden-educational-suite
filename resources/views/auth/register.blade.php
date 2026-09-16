<x-layouts.guest :title="__('Create your account')" :school="$school ?? null">
    <x-auth-heading
        :title="__('Create your account')"
        :description="isset($school) && $school ? __('Set up your login for :school. Your school links your account afterwards.', ['school' => $school->name]) : __('Set up your login. School details come later.')"
    />

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <x-input name="name" :label="__('Full name')" autocomplete="name" required autofocus />

        <x-input
            name="email"
            type="email"
            :label="__('Email address')"
            autocomplete="username"
            required
        />

        <x-input
            name="password"
            type="password"
            :label="__('Password')"
            autocomplete="new-password"
            required
            :hint="__('At least 8 characters, including a letter and a number.')"
        />

        <x-input
            name="password_confirmation"
            type="password"
            :label="__('Confirm password')"
            autocomplete="new-password"
            required
        />

        <x-button type="submit" class="w-full">{{ __('Create account') }}</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        {{ __('Already have an account?') }}
        <a href="{{ route('login', isset($school) && $school ? ['school' => $school->slug] : []) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ __('Sign in') }}</a>
    </p>
</x-layouts.guest>
