<x-layouts.authenticated :title="__('Dashboard')">
    <div class="space-y-6">
        <x-card :title="__('Welcome, :name', ['name' => auth()->user()->name])">
            <p class="text-sm text-gray-600">
                {{ __('Your account is set up and verified. School management features arrive in upcoming releases.') }}
            </p>
        </x-card>

        <x-empty-state
            :title="__('Nothing to show yet')"
            :description="__('This is a placeholder dashboard. Once your school is set up, this area will show the things that need your attention.')"
        >
            <x-slot:actions>
                <x-button :href="route('settings.profile.edit')" variant="secondary" size="sm">
                    {{ __('Review account settings') }}
                </x-button>
            </x-slot:actions>
        </x-empty-state>
    </div>
</x-layouts.authenticated>
