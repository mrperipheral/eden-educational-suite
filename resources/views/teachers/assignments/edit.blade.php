<x-layouts.authenticated :title="__('Edit assignment')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('teachers.show', $teacher->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← Back to :name', ['name' => $teacher->shortName()]) }}</a>
        </p>

        <x-card :title="__('Edit assignment')">
            <form method="POST" action="{{ route('teachers.assignments.update', $assignment->id) }}">
                @csrf
                @method('PATCH')

                @include('teachers.assignments._form')

                <div class="flex items-center gap-2 pt-4">
                    <x-button type="submit">{{ __('Save changes') }}</x-button>
                    <x-button :href="route('teachers.show', $teacher->id)" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
