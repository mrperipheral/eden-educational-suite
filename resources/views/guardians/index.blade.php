<x-layouts.authenticated :title="__('Guardians')">
    @can('guardian.manage')
        <x-slot:actions>
            <x-button :href="route('guardians.create')" size="sm">{{ __('Add guardian') }}</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <form method="GET" action="{{ route('guardians.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Name, phone or email…') }}"
                class="block w-full max-w-xs rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
            <x-button type="submit" variant="secondary">{{ __('Search') }}</x-button>
        </form>

        @if ($guardians->isEmpty())
            <x-empty-state
                :title="$search !== '' ? __('No guardians match') : __('No guardians yet')"
                :description="$search !== ''
                    ? __('Try a different search.')
                    : __('Add a parent or guardian, then link them to their children from a student\'s profile.')"
            >
                @can('guardian.manage')
                    <x-slot:actions>
                        <x-button :href="route('guardians.create')" size="sm">{{ __('Add guardian') }}</x-button>
                    </x-slot:actions>
                @endcan
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($guardians as $guardian)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route('guardians.show', $guardian->id) }}" class="hover:text-brand-700">
                                        {{ $guardian->displayName() }} {{ $guardian->last_name }}
                                    </a>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $guardian->phone ?: $guardian->email ?: __('no contact details') }}
                                    · {{ trans_choice(':count linked student|:count linked students', $guardian->students_count, ['count' => $guardian->students_count]) }}
                                </p>
                            </div>
                            <div class="shrink-0">
                                <x-button :href="route('guardians.show', $guardian->id)" size="sm" variant="secondary">{{ __('View') }}</x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $guardians->links() }}
        @endif
    </div>
</x-layouts.authenticated>
