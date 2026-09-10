<x-layouts.authenticated :title="__('Students')">
    @can('student.manage')
        <x-slot:actions>
            <x-button :href="route('students.create')" size="sm">{{ __('Add student') }}</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <form method="GET" action="{{ route('students.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Name or admission number…') }}"
                class="block w-full max-w-xs rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
            <x-button type="submit" variant="secondary">{{ __('Search') }}</x-button>

            <span class="mx-1 hidden text-gray-300 sm:inline">|</span>

            <a href="{{ route('students.index', ['q' => $search]) }}"
                @class([
                    'rounded-md px-2.5 py-1 text-xs font-medium',
                    'bg-brand-600 text-white' => ! $status,
                    'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' => (bool) $status,
                ])>{{ __('All') }}</a>
            @foreach (\App\Enums\StudentStatus::all() as $s)
                <a href="{{ route('students.index', ['q' => $search, 'status' => $s->value]) }}"
                    @class([
                        'rounded-md px-2.5 py-1 text-xs font-medium',
                        'bg-brand-600 text-white' => $status === $s,
                        'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' => $status !== $s,
                    ])>{{ $s->label() }}</a>
            @endforeach
        </form>

        @if ($students->isEmpty())
            <x-empty-state
                :title="$search !== '' || $status ? __('No students match') : __('No students yet')"
                :description="$search !== '' || $status
                    ? __('Try a different search or filter.')
                    : __('Add the school\'s students to start building enrollment history.')"
            />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($students as $student)
                        @php($placement = $student->currentEnrollment)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route('students.show', $student->id) }}" class="hover:text-brand-700">
                                        {{ $student->displayName() }} {{ $student->last_name }}
                                    </a>
                                    <x-badge :variant="$student->status->badgeVariant()" class="ml-1">{{ $student->status->label() }}</x-badge>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <span class="font-mono">{{ $student->admission_number }}</span>
                                    @if ($placement)
                                        · {{ $placement->level?->name }}{{ $placement->arm ? ' — '.$placement->arm->name : '' }}
                                    @else
                                        · <span class="text-gray-400">{{ __('not placed') }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="shrink-0">
                                <x-button :href="route('students.show', $student->id)" size="sm" variant="secondary">{{ __('View') }}</x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $students->links() }}
        @endif
    </div>
</x-layouts.authenticated>
