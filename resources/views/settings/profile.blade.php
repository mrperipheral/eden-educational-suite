<x-layouts.authenticated :title="__('Account settings')">
    <div class="max-w-2xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card :title="__('Profile information')">
            @include('settings.partials.update-profile-information-form')
        </x-card>

        <x-card :title="__('Update password')">
            @include('settings.partials.update-password-form')
        </x-card>

        <x-card :title="__('Delete account')">
            @include('settings.partials.delete-user-form')
        </x-card>
    </div>
</x-layouts.authenticated>
