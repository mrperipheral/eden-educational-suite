<x-layouts.authenticated :title="__('Add teacher')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('teachers.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Teachers') }}</a>
        </p>

        <x-card :title="__('New teacher')">
            <form method="POST" action="{{ route('teachers.store') }}">
                @csrf

                @include('teachers._form')

                <div class="flex items-center gap-2 pt-6">
                    <x-button type="submit">{{ __('Add teacher') }}</x-button>
                    <x-button :href="route('teachers.index')" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
