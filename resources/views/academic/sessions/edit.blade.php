<x-layouts.authenticated :title="__('Edit session')">
    <div class="max-w-xl space-y-6">
        @include('academic._nav')

        <x-card :title="__('Edit :name', ['name' => $session->name])">
            <form method="POST" action="{{ route('academic.sessions.update', $session->id) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <x-input name="name" :label="__('Name')" :value="old('name', $session->name)" required />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-input name="starts_on" type="date" :label="__('Starts on')"
                        :value="old('starts_on', $session->starts_on->toDateString())" required />
                    <x-input name="ends_on" type="date" :label="__('Ends on')"
                        :value="old('ends_on', $session->ends_on->toDateString())" required />
                </div>

                @unless ($session->is_current)
                    <x-checkbox name="is_current" :label="__('Make this the current session')" :checked="old('is_current')" />
                @endunless

                <div class="flex items-center gap-2 pt-1">
                    <x-button type="submit">{{ __('Save changes') }}</x-button>
                    <x-button :href="route('academic.sessions.index')" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
