<x-layouts.authenticated :title="__('Edit arm')">
    <div class="max-w-xl space-y-6">
        @include('academic._nav')

        <x-card :title="__('Edit :name', ['name' => $arm->name])">
            <p class="mb-4 text-xs text-gray-500">{{ __('Level: :name', ['name' => $arm->level->name]) }}</p>

            <form method="POST" action="{{ route('academic.arms.update', $arm->id) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-3">
                        <x-input name="name" :label="__('Name')" :value="old('name', $arm->name)" required />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input name="code" :label="__('Code')" :value="old('code', $arm->code)" required />
                    </div>
                    <x-input name="position" type="number" min="1" :label="__('Order')" :value="old('position', $arm->position)" required />
                </div>

                <x-checkbox name="is_active" :label="__('Active')" :checked="old('is_active', $arm->is_active)" />

                <div class="flex items-center gap-2 pt-1">
                    <x-button type="submit">{{ __('Save changes') }}</x-button>
                    <x-button :href="route('academic.levels.show', $arm->academic_level_id)" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
