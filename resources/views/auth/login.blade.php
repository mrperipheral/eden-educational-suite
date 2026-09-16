<x-layouts.guest :title="__('Sign in')" :school="$school ?? null">
    <x-auth-heading
        :title="__('Sign in')"
        :description="isset($school) && $school ? __('Sign in to :school.', ['school' => $school->name]) : __('Welcome back. Enter your details to continue.')"
    />

    @if (session('status'))
        <x-alert variant="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <x-input
            name="email"
            type="email"
            :label="__('Email address')"
            autocomplete="username"
            required
            autofocus
        />

        <div class="space-y-1">
            <div class="flex items-center justify-between">
                <label for="password" class="block text-sm font-medium text-gray-700">{{ __('Password') }}</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">
                        {{ __('Forgot password?') }}
                    </a>
                @endif
            </div>
            <x-input name="password" type="password" id="password" autocomplete="current-password" required :label="null" />
        </div>

        <x-checkbox name="remember" :label="__('Remember me on this device')" />

        <x-button type="submit" class="w-full">{{ __('Sign in') }}</x-button>
    </form>

    @if (Route::has('register'))
        <p class="mt-6 text-center text-sm text-gray-500">
            {{ __("Don't have an account?") }}
            <a href="{{ route('register', isset($school) && $school ? ['school' => $school->slug] : []) }}" class="font-medium text-brand-600 hover:text-brand-700">{{ __('Create one') }}</a>
        </p>
    @endif
</x-layouts.guest>
