<x-layouts.authenticated :title="__('Schools')">
    <x-slot:actions>
        <x-button :href="route('admin.schools.create')" size="sm">{{ __('Create school') }}</x-button>
    </x-slot:actions>

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <form method="GET" action="{{ route('admin.schools.index') }}" class="flex gap-2">
            <input
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="{{ __('Search by name or slug') }}"
                class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500"
            >
            <x-button type="submit" variant="secondary">{{ __('Search') }}</x-button>
        </form>

        @if ($schools->isEmpty())
            <x-empty-state
                :title="__('No schools')"
                :description="$search ? __('No schools match your search.') : __('Create the first school to get started.')"
            >
                @unless ($search)
                    <x-slot:actions>
                        <x-button :href="route('admin.schools.create')" size="sm">{{ __('Create school') }}</x-button>
                    </x-slot:actions>
                @endunless
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($schools as $school)
                        <li class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                            <div class="min-w-0">
                                <a href="{{ route('admin.schools.show', $school) }}" class="truncate text-sm font-medium text-brand-700 hover:underline">
                                    {{ $school->name }}
                                </a>
                                <p class="truncate text-xs text-gray-500">
                                    {{ $school->slug }} · {{ trans_choice(':count member|:count members', $school->users_count, ['count' => $school->users_count]) }}
                                </p>
                            </div>
                            <x-badge :variant="$school->isActive() ? 'success' : 'warning'">
                                {{ $school->status->label() }}
                            </x-badge>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $schools->links() }}
        @endif
    </div>
</x-layouts.authenticated>
