@php
    // Shared by the staff, Parent Portal and Student Portal routes — resolve
    // route names relative to whichever one matched (see
    // App\Http\Controllers\NotificationController).
    $routeBase = str(request()->route()->getName())->beforeLast('.')->toString();
@endphp

<x-layouts.authenticated :title="__('Notifications')">
    @if ($unreadCount > 0)
        <x-slot:actions>
            <form method="POST" action="{{ route($routeBase.'.read-all') }}">
                @csrf
                <x-button type="submit" size="sm" variant="secondary">{{ __('Mark all read') }}</x-button>
            </form>
        </x-slot:actions>
    @endif

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if ($notifications->isEmpty())
            <x-empty-state
                :title="__('No notifications yet')"
                :description="__('Announcements and communication activity that concern you will show up here.')"
            />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($notifications as $notification)
                        <li class="px-4 py-3 sm:px-6 {{ $notification->isRead() ? '' : 'bg-brand-50/40' }}">
                            <form method="POST" action="{{ route($routeBase.'.read', $notification) }}" class="flex items-start justify-between gap-3">
                                @csrf
                                <button type="submit" class="min-w-0 flex-1 text-left">
                                    <p class="flex items-center gap-2 text-sm font-medium text-gray-900">
                                        @unless ($notification->isRead())
                                            <span class="h-2 w-2 shrink-0 rounded-full bg-brand-600" aria-hidden="true"></span>
                                        @endunless
                                        {{ $notification->title }}
                                    </p>
                                    <p class="mt-0.5 truncate text-sm text-gray-600">{{ $notification->message }}</p>
                                    <p class="mt-0.5 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $notifications->links() }}
        @endif
    </div>
</x-layouts.authenticated>
