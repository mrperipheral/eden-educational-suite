<x-layouts.authenticated :title="__('Edit enrollment')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('students.show', $student->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← Back to :name', ['name' => $student->shortName()]) }}</a>
        </p>

        <x-card :title="__('Edit enrollment')">
            <form method="POST" action="{{ route('students.enrollments.update', $enrollment->id) }}">
                @csrf
                @method('PATCH')

                @include('students.enrollments._form')

                <div class="flex items-center gap-2 pt-4">
                    <x-button type="submit">{{ __('Save changes') }}</x-button>
                    <x-button :href="route('students.show', $student->id)" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
