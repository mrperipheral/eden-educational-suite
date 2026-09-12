@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<x-layouts.authenticated :title="__('Audit Log')">
    <x-slot:actions>
        <x-button :href="route('audit-log.export', $filters)" size="sm" variant="secondary">{{ __('Export CSV') }}</x-button>
    </x-slot:actions>

    <div class="space-y-6">
        <x-alert variant="info">
            {{ __('A chronological record of administrative and security actions taken in this school. Entries cannot be edited or deleted.') }}
        </x-alert>

        <form method="GET" action="{{ route('audit-log.index') }}" class="flex flex-wrap items-end gap-2">
            <div class="space-y-1">
                <label for="q" class="block text-xs font-medium text-gray-700">{{ __('Search') }}</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Summary, actor or record…') }}" class="{{ $selectClass }}">
            </div>
            <div class="space-y-1">
                <label for="event" class="block text-xs font-medium text-gray-700">{{ __('Event') }}</label>
                <select id="event" name="event" class="{{ $selectClass }}">
                    <option value="">{{ __('All events') }}</option>
                    @foreach ($events as $event)
                        <option value="{{ $event }}" @selected(($filters['event'] ?? null) === $event)>{{ $event }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="actor" class="block text-xs font-medium text-gray-700">{{ __('User') }}</label>
                <select id="actor" name="actor" class="{{ $selectClass }}">
                    <option value="">{{ __('All users') }}</option>
                    @foreach ($actors as $actor)
                        <option value="{{ $actor->id }}" @selected(($filters['actor'] ?? null) == $actor->id)>{{ $actor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="type" class="block text-xs font-medium text-gray-700">{{ __('Affected type') }}</label>
                <select id="type" name="type" class="{{ $selectClass }}">
                    <option value="">{{ __('All types') }}</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['type'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="from" class="block text-xs font-medium text-gray-700">{{ __('From') }}</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="{{ $selectClass }}">
            </div>
            <div class="space-y-1">
                <label for="to" class="block text-xs font-medium text-gray-700">{{ __('To') }}</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="{{ $selectClass }}">
            </div>
            <x-button type="submit" size="sm" variant="secondary">{{ __('Filter') }}</x-button>
            @if (array_filter($filters))
                <x-button :href="route('audit-log.index')" size="sm" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        @if ($logs->isEmpty())
            <x-empty-state
                :title="__('No audit entries match')"
                :description="__('Administrative and security actions taken in this school will appear here.')"
            />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($logs as $log)
                        <li class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm text-gray-900">
                                    <a href="{{ route('audit-log.show', $log->id) }}" class="font-medium hover:text-brand-700">{{ $log->summary }}</a>
                                </p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <x-badge variant="gray">{{ $log->event }}</x-badge>
                                    · {{ $log->actor_name ?? __('System') }}
                                    · {{ $log->created_at?->format('d M Y, H:i') }}
                                    @if ($log->auditable_label)
                                        · {{ $log->auditable_label }}
                                    @endif
                                </p>
                            </div>
                            <div class="shrink-0">
                                <x-button :href="route('audit-log.show', $log->id)" size="sm" variant="ghost">{{ __('Details') }}</x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $logs->links() }}
        @endif
    </div>
</x-layouts.authenticated>
