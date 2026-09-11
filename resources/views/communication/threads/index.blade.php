@php
    $selectClass = 'rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $filtering = $filters['status'] || $filters['category'];
@endphp

<x-layouts.authenticated :title="__('Communication Hub')">
    @can('communication.create')
        <x-slot:actions>
            <x-button :href="route('communication.threads.create')" size="sm">{{ __('New thread') }}</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <form method="GET" action="{{ route('communication.threads.index') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Status') }}</label>
                <select name="status" class="{{ $selectClass }}">
                    <option value="">{{ __('Any') }}</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected($filters['status'] === $s)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Category') }}</label>
                <select name="category" class="{{ $selectClass }}">
                    <option value="">{{ __('Any') }}</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->value }}" @selected($filters['category'] === $c)>{{ $c->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" variant="secondary">{{ __('Filter') }}</x-button>
            @if ($filtering)
                <x-button :href="route('communication.threads.index')" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        @if ($threads->isEmpty())
            <x-empty-state
                :title="$filtering ? __('No threads match') : __('No communication logged yet')"
                :description="$filtering ? __('Try a different filter.') : __('Log a call, message or in-person conversation with a parent or about a student.')"
            >
                @can('communication.create')
                    @unless ($filtering)
                        <x-slot:actions>
                            <x-button :href="route('communication.threads.create')" size="sm">{{ __('New thread') }}</x-button>
                        </x-slot:actions>
                    @endunless
                @endcan
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($threads as $thread)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route('communication.threads.show', $thread) }}" class="hover:text-brand-700">
                                        {{ $thread->subject }}
                                    </a>
                                    <x-badge :variant="$thread->status->badgeVariant()" class="ml-1">{{ $thread->status->label() }}</x-badge>
                                    <x-badge variant="gray">{{ $thread->category->label() }}</x-badge>
                                </p>
                                <p class="text-xs text-gray-500">
                                    @if ($thread->student)
                                        {{ __('Student') }}: {{ $thread->student->displayName() }}
                                    @endif
                                    @if ($thread->guardian)
                                        <span @if($thread->student) class="ml-1" @endif>{{ __('Guardian') }}: {{ $thread->guardian->fullName() }}</span>
                                    @endif
                                    @if ($thread->assignedTo)
                                        <span class="ml-1">· {{ __('Assigned to') }} {{ $thread->assignedTo->name }}</span>
                                    @endif
                                    @if ($thread->last_message_at)
                                        <span class="ml-1">· {{ $thread->last_message_at->diffForHumans() }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="shrink-0">
                                <x-button :href="route('communication.threads.show', $thread)" size="sm" variant="secondary">{{ __('Open') }}</x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $threads->links() }}
        @endif
    </div>
</x-layouts.authenticated>
