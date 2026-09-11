<x-layouts.authenticated :title="__('New announcement')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('announcements.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Announcements') }}</a>
        </p>

        <x-card :title="__('New announcement')">
            @php($announcement = new \App\Models\Announcement)
            <form method="POST" action="{{ route('announcements.store') }}">
                @csrf

                @include('communication.announcements._form')

                <div class="flex items-center gap-2 pt-6">
                    <x-button type="submit">{{ __('Save as draft') }}</x-button>
                    <x-button :href="route('announcements.index')" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
