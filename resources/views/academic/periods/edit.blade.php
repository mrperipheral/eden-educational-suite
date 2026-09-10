<x-layouts.authenticated :title="__('Edit term')">
    <div class="max-w-xl space-y-6">
        @include('academic._nav')

        <x-card :title="__('Edit :name', ['name' => $period->name])">
            <p class="mb-4 text-xs text-gray-500">{{ __('Session: :name', ['name' => $period->session->name]) }}</p>

            <form method="POST" action="{{ route('academic.periods.update', $period->id) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <x-input name="name" :label="__('Name')" :value="old('name', $period->name)" required />
                    </div>
                    <x-input name="position" type="number" min="1" :label="__('Order')" :value="old('position', $period->position)" required />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-input name="starts_on" type="date" :label="__('Starts on')"
                        :value="old('starts_on', $period->starts_on->toDateString())" required />
                    <x-input name="ends_on" type="date" :label="__('Ends on')"
                        :value="old('ends_on', $period->ends_on->toDateString())" required />
                </div>

                <x-checkbox name="is_active" :label="__('Active')" :checked="old('is_active', $period->is_active)" />

                <div class="flex items-center gap-2 pt-1">
                    <x-button type="submit">{{ __('Save changes') }}</x-button>
                    <x-button :href="route('academic.sessions.show', $period->academic_session_id)" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
