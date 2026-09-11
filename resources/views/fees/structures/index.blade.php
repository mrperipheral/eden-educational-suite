@php
    $selectClass = 'rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $money = fn ($n) => number_format((float) $n, 2);
    $filtering = $filters['session'] || $filters['level'];
@endphp

<x-layouts.authenticated :title="__('Fee structures')">
    <x-slot:actions>
        <x-button :href="route('fees.categories.index')" size="sm" variant="ghost">{{ __('Categories') }}</x-button>
        <x-button :href="route('fees.structures.create')" size="sm">{{ __('New structure') }}</x-button>
    </x-slot:actions>

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        <p class="text-sm">
            <a href="{{ route('fees.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Fees') }}</a>
        </p>

        <form method="GET" action="{{ route('fees.structures.index') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Session') }}</label>
                <select name="session" class="{{ $selectClass }}">
                    <option value="">{{ __('All sessions') }}</option>
                    @foreach ($sessions as $s)
                        <option value="{{ $s->id }}" @selected($filters['session'] === $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Level') }}</label>
                <select name="level" class="{{ $selectClass }}">
                    <option value="">{{ __('All levels') }}</option>
                    @foreach ($levels as $l)
                        <option value="{{ $l->id }}" @selected($filters['level'] === $l->id)>{{ $l->name }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" variant="secondary">{{ __('Filter') }}</x-button>
            @if ($filtering)
                <x-button :href="route('fees.structures.index')" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        @if ($structures->isEmpty())
            <x-empty-state
                :title="$filtering ? __('No structures match') : __('No fee structures yet')"
                :description="$filtering ? __('Try a different filter.') : __('Define what each category costs for a session, term and level.')"
            >
                @unless ($filtering)
                    <x-slot:actions>
                        <x-button :href="route('fees.structures.create')" size="sm">{{ __('New structure') }}</x-button>
                    </x-slot:actions>
                @endunless
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($structures as $structure)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route('fees.structures.edit', $structure) }}" class="hover:text-brand-700">
                                        {{ $structure->category?->name }}
                                    </a>
                                    <span class="text-gray-400">·</span> {{ $money($structure->amount) }}
                                    @unless ($structure->is_mandatory) <x-badge variant="gray">{{ __('Optional') }}</x-badge> @endunless
                                    @unless ($structure->is_active) <x-badge variant="gray">{{ __('Inactive') }}</x-badge> @endunless
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $structure->session?->name }}{{ $structure->period ? ' · '.$structure->period->name : ' · '.__('Whole session') }}
                                    · {{ $structure->level?->name }}{{ $structure->arm ? ' — '.$structure->arm->name : '' }}
                                </p>
                            </div>
                            <div class="shrink-0">
                                <x-button :href="route('fees.structures.edit', $structure)" size="sm" variant="secondary">{{ __('Edit') }}</x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $structures->links() }}
        @endif
    </div>
</x-layouts.authenticated>
