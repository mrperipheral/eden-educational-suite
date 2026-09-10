<x-layouts.authenticated :title="__('Dashboard')">
    <div class="space-y-6">
        <x-card :title="__('Current school')">
            <p class="text-sm text-gray-700">
                {{ __('You are working in') }}
                <strong>{{ $school->name }}</strong>.
            </p>
            <p class="mt-1 text-xs text-gray-500">
                {{ __('Everything you see and do is scoped to this school. Other schools\' data is never visible here.') }}
            </p>
        </x-card>

        <x-empty-state
            :title="__('Nothing to show yet')"
            :description="__('This is a placeholder dashboard. Once the school modules are built, this area will show what needs your attention in :school.', ['school' => $school->name])"
        >
            <x-slot:actions>
                <x-button :href="route('settings.profile.edit')" variant="secondary" size="sm">
                    {{ __('Account settings') }}
                </x-button>
            </x-slot:actions>
        </x-empty-state>
    </div>
</x-layouts.authenticated>
