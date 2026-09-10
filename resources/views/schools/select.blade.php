<x-layouts.authenticated :title="__('Choose a school')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm text-gray-600">
            {{ auth()->user()->isPlatformAdmin()
                ? __('Select the school you want to work in. You can switch at any time.')
                : __('Your account has access to more than one school. Choose the one you want to work in.') }}
        </p>

        <form method="GET" action="{{ route('school-context.create') }}" class="flex gap-2">
            <input
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="{{ __('Search schools') }}"
                class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500"
            >
            <x-button type="submit" variant="secondary">{{ __('Search') }}</x-button>
        </form>

        @if ($schools->isEmpty())
            <x-empty-state
                :title="__('No schools found')"
                :description="$search
                    ? __('No schools match your search.')
                    : __('Your account is not linked to any school yet. Contact your administrator.')"
            />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($schools as $school)
                        <li class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">{{ $school->name }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $school->status->label() }}@if ((int) $currentSchoolId === (int) $school->id) · {{ __('current') }}@endif
                                </p>
                            </div>

                            @if ($school->isActive())
                                <form method="POST" action="{{ route('school-context.store') }}">
                                    @csrf
                                    <input type="hidden" name="school" value="{{ $school->id }}">
                                    <x-button type="submit" size="sm">{{ __('Enter') }}</x-button>
                                </form>
                            @else
                                <x-badge variant="warning">{{ __('Suspended') }}</x-badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $schools->links() }}
        @endif
    </div>
</x-layouts.authenticated>
