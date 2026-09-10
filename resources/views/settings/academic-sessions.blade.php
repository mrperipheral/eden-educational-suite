<x-layouts.authenticated :title="__('Academic sessions')">
    <div class="max-w-2xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <nav class="flex gap-4 border-b border-gray-200 pb-2 text-sm">
            <a href="{{ route('settings.school.edit') }}" class="text-gray-500 hover:text-gray-800">{{ __('General') }}</a>
            <span class="font-medium text-brand-700">{{ __('Academic sessions') }}</span>
        </nav>

        @can('school.settings.update')
            <x-card :title="__('Add a session')">
                <form method="POST" action="{{ route('academic-sessions.store') }}" class="space-y-4">
                    @csrf

                    <x-input name="name" :label="__('Name')" :value="old('name')" required placeholder="2025/2026" :hint="__('Any label you use for the year. No fixed structure.')" />

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
                :description="__('Create the school\'s first academic session to finish onboarding.')"
            />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($sessions as $session)
                        <li class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    {{ $session->name }}
                                    @if ($session->is_current)
                                        <x-badge variant="success" class="ml-1">{{ __('Current') }}</x-badge>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $session->starts_on->toFormattedDateString() }} – {{ $session->ends_on->toFormattedDateString() }}
                                </p>
                            </div>

                            @if (! $session->is_current)
                                @can('school.settings.update')
                                    <form method="POST" action="{{ route('academic-sessions.update', $session) }}">
                                        @csrf
                                        @method('PATCH')
                                        <x-button type="submit" size="sm" variant="secondary">{{ __('Make current') }}</x-button>
                                    </form>
                                @endcan
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $sessions->links() }}
        @endif
    </div>
</x-layouts.authenticated>
