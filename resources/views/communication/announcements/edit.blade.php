<x-layouts.authenticated :title="__('Edit announcement')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('announcements.show', $announcement) }}" class="text-brand-600 hover:text-brand-700">{{ __('← :title', ['title' => $announcement->title]) }}</a>
        </p>

        <x-card :title="__('Edit announcement')">
            <form method="POST" action="{{ route('announcements.update', $announcement) }}">
                @csrf
                @method('PATCH')

                @include('communication.announcements._form')

                <div class="flex items-center gap-2 pt-6">
                    <x-button type="submit">{{ __('Save changes') }}</x-button>
                    <x-button :href="route('announcements.show', $announcement)" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
