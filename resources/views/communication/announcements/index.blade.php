@php
    // Shared by the staff, Parent Portal and Student Portal routes — resolve
    // route names relative to whichever one matched (see
    // App\Http\Controllers\Communication\AnnouncementController).
    $routeBase = str(request()->route()->getName())->beforeLast('.')->toString();
@endphp

<x-layouts.authenticated :title="__('Announcements')">
    @if ($canManage)
        <x-slot:actions>
            <x-button :href="route('announcements.create')" size="sm">{{ __('New announcement') }}</x-button>
        </x-slot:actions>
    @endif

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if ($announcements->isEmpty())
            <x-empty-state
                :title="__('No announcements yet')"
                :description="__('Nothing has been published for you to see here yet.')"
            />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($announcements as $announcement)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route($routeBase.'.show', $announcement) }}" class="hover:text-brand-700">
                                        {{ $announcement->title }}
                                    </a>
                                    <x-badge :variant="$announcement->status->badgeVariant()" class="ml-1">{{ $announcement->status->label() }}</x-badge>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $announcement->audience->label() }}
                                    @if ($announcement->published_at)
                                        · {{ __('Published') }} {{ $announcement->published_at->diffForHumans() }}
                                    @else
                                        · {{ __('Draft') }}
                                    @endif
                                </p>
                            </div>
                            <div class="shrink-0">
                                <x-button :href="route($routeBase.'.show', $announcement)" size="sm" variant="secondary">{{ __('Open') }}</x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $announcements->links() }}
        @endif
    </div>
</x-layouts.authenticated>
