<x-layouts.authenticated :title="__('Academic sessions')">
    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @include('academic._nav')

        @can('academics.manage')
            <x-card :title="__('Add a session')">
                <form method="POST" action="{{ route('academic.sessions.store') }}" class="space-y-4">
                    @csrf

                    <x-input name="name" :label="__('Name')" :value="old('name')" required placeholder="2025/2026"
                        :hint="__('Any label your school uses for the year.')" />

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input name="starts_on" type="date" :label="__('Starts on')" :value="old('starts_on')" required />
                        <x-input name="ends_on" type="date" :label="__('Ends on')" :value="old('ends_on')" required />
                    </div>

                    <x-checkbox name="is_current" :label="__('Make this the current session')" :checked="old('is_current')" />

                    <div class="pt-1">
                        <x-button type="submit">{{ __('Add session') }}</x-button>
                    </div>
                </form>
            </x-card>
        @endcan

        @if ($sessions->isEmpty())
            <x-empty-state
                :title="__('No academic sessions yet')"
                :description="__('Create the school\'s first academic session to start building its academic structure.')"
            />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($sessions as $session)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    {{ $session->name }}
                                    @if ($session->is_current)
                                        <x-badge variant="success" class="ml-1">{{ __('Current') }}</x-badge>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $session->starts_on->toFormattedDateString() }} – {{ $session->ends_on->toFormattedDateString() }}
                                    · {{ trans_choice('{0}no terms|{1}:count term|[2,*]:count terms', $session->periods_count, ['count' => $session->periods_count]) }}
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                @if (! $session->is_current)
                                    @can('academics.manage')
                                        <form method="POST" action="{{ route('academic.sessions.current', $session->id) }}">
                                            @csrf
                                            @method('PUT')
                                            <x-button type="submit" size="sm" variant="secondary">{{ __('Make current') }}</x-button>
                                        </form>
                                    @endcan
                                @endif
                                <x-button :href="route('academic.sessions.show', $session->id)" size="sm" variant="secondary">
                                    {{ __('Terms') }}
                                </x-button>
                                @can('academics.manage')
                                    <x-button :href="route('academic.sessions.edit', $session->id)" size="sm" variant="ghost">
                                        {{ __('Edit') }}
                                    </x-button>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $sessions->links() }}
        @endif
    </div>
</x-layouts.authenticated>
