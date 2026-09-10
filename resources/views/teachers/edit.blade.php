<x-layouts.authenticated :title="__('Edit teacher')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('teachers.show', $teacher->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← Back to profile') }}</a>
        </p>

        <x-card :title="__('Edit :name', ['name' => $teacher->shortName()])">
            <form method="POST" action="{{ route('teachers.update', $teacher->id) }}">
                @csrf
                @method('PATCH')

                @include('teachers._form')

                <div class="flex items-center gap-2 pt-6">
                    <x-button type="submit">{{ __('Save changes') }}</x-button>
                    <x-button :href="route('teachers.show', $teacher->id)" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
