@php
    $routeBase = str(request()->route()->getName())->beforeLast('.')->toString();
@endphp

<x-layouts.authenticated :title="$announcement->title">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route($routeBase.'.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Announcements') }}</a>
        </p>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <x-badge :variant="$announcement->status->badgeVariant()">{{ $announcement->status->label() }}</x-badge>
                <x-badge variant="gray">{{ $announcement->audience->label() }}</x-badge>
            </div>

            <h1 class="mt-3 text-lg font-semibold text-gray-900">{{ $announcement->title }}</h1>
            <p class="mt-1 text-xs text-gray-500">
                {{ $announcement->createdBy?->name }}
                @if ($announcement->published_at)
                    · {{ __('Published') }} {{ $announcement->published_at->toFormattedDateString() }}
                @endif
            </p>

            <div class="prose prose-sm mt-4 max-w-none whitespace-pre-line text-gray-900">{{ $announcement->body }}</div>

            @if ($canManage)
                <div class="mt-6 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                    <x-button :href="route('announcements.edit', $announcement)" size="sm" variant="secondary">{{ __('Edit') }}</x-button>
                    @if ($announcement->isPublished())
                        <form method="POST" action="{{ route('announcements.unpublish', $announcement) }}">
                            @csrf
                            <x-button type="submit" size="sm" variant="ghost">{{ __('Unpublish') }}</x-button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('announcements.publish', $announcement) }}">
                            @csrf
                            <x-button type="submit" size="sm">{{ __('Publish') }}</x-button>
                        </form>
                    @endif
                </div>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
