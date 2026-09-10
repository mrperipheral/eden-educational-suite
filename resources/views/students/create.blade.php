<x-layouts.authenticated :title="__('Add student')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('students.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Students') }}</a>
        </p>

        <x-card :title="__('New student')">
            <form method="POST" action="{{ route('students.store') }}">
                @csrf

                @include('students._form')

                <div class="flex items-center gap-2 pt-6">
                    <x-button type="submit">{{ __('Add student') }}</x-button>
                    <x-button :href="route('students.index')" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
