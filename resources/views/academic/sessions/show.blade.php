<x-layouts.authenticated :title="__('Terms in :name', ['name' => $session->name])">
    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @include('academic._nav')

        <x-card :title="__('Session')">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
                <dt class="text-gray-500">{{ __('Name') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">
                    {{ $session->name }}
                    @if ($session->is_current)
                        <x-badge variant="success" class="ml-1">{{ __('Current') }}</x-badge>
                    @endif
                </dd>
                <dt class="text-gray-500">{{ __('Runs') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">
                    {{ $session->starts_on->toFormattedDateString() }} – {{ $session->ends_on->toFormattedDateString() }}
                </dd>
            </dl>
        </x-card>

        @can('academics.manage')
            <x-card :title="__('Add a term / period')">
                <form method="POST" action="{{ route('academic.periods.store', $session->id) }}" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <x-input name="name" :label="__('Name')" :value="old('name')" required placeholder="First Term"
                                :hint="__('Term, semester, trimester — your school\'s wording.')" />
                        </div>
                        <x-input name="position" type="number" min="1" :label="__('Order')" :value="old('position')" required />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input name="starts_on" type="date" :label="__('Starts on')" :value="old('starts_on')" required />
                        <x-input name="ends_on" type="date" :label="__('Ends on')" :value="old('ends_on')" required />
                    </div>

                    <div class="pt-1">
                        <x-button type="submit">{{ __('Add term') }}</x-button>
                    </div>
                </form>
            </x-card>
        @endcan

        @if ($periods->isEmpty())
            <x-empty-state
                :title="__('No terms in this session')"
                :description="__('Add as many periods as this school\'s calendar needs — there is no fixed number.')"
            />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($periods as $period)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <span class="text-gray-400">{{ $period->position }}.</span>
                                    {{ $period->name }}
                                    @if ($period->is_current)
                                        <x-badge variant="success" class="ml-1">{{ __('Current') }}</x-badge>
                                    @endif
                                    @unless ($period->is_active)
                                        <x-badge variant="warning" class="ml-1">{{ __('Inactive') }}</x-badge>
                                    @endunless
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $period->starts_on->toFormattedDateString() }} – {{ $period->ends_on->toFormattedDateString() }}
                                </p>
                            </div>

                            @can('academics.manage')
                                <div class="flex shrink-0 items-center gap-2">
                                    @if (! $period->is_current && $period->is_active)
                                        <form method="POST" action="{{ route('academic.periods.current', $period->id) }}">
                                            @csrf
                                            @method('PUT')
                                            <x-button type="submit" size="sm" variant="secondary">{{ __('Make current') }}</x-button>
                                        </form>
                                    @endif
                                    <x-button :href="route('academic.periods.edit', $period->id)" size="sm" variant="ghost">
                                        {{ __('Edit') }}
                                    </x-button>
                                </div>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <p class="text-sm">
            <a href="{{ route('academic.sessions.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← All sessions') }}</a>
        </p>
    </div>
</x-layouts.authenticated>
