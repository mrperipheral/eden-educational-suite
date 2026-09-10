<x-layouts.authenticated :title="__('Teachers')">
    @can('staff.manage')
        <x-slot:actions>
            <x-button :href="route('teachers.create')" size="sm">{{ __('Add teacher') }}</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <form method="GET" action="{{ route('teachers.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Name, employee number or email…') }}"
                class="block w-full max-w-xs rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
            <x-button type="submit" variant="secondary">{{ __('Search') }}</x-button>

            <span class="mx-1 hidden text-gray-300 sm:inline">|</span>

            <a href="{{ route('teachers.index', ['q' => $search]) }}"
                @class([
                    'rounded-md px-2.5 py-1 text-xs font-medium',
                    'bg-brand-600 text-white' => ! $status,
                    'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' => (bool) $status,
                ])>{{ __('All') }}</a>
            @foreach (\App\Enums\TeacherStatus::all() as $s)
                <a href="{{ route('teachers.index', ['q' => $search, 'status' => $s->value]) }}"
                    @class([
                        'rounded-md px-2.5 py-1 text-xs font-medium',
                        'bg-brand-600 text-white' => $status === $s,
                        'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' => $status !== $s,
                    ])>{{ $s->label() }}</a>
            @endforeach
        </form>

        @if ($teachers->isEmpty())
            <x-empty-state
                :title="$search !== '' || $status ? __('No teachers match') : __('No teachers yet')"
                :description="$search !== '' || $status
                    ? __('Try a different search or filter.')
                    : __('Add the school\'s teaching staff to start recording assignments.')"
            >
                @can('staff.manage')
                    <x-slot:actions>
                        <x-button :href="route('teachers.create')" size="sm">{{ __('Add teacher') }}</x-button>
                    </x-slot:actions>
                @endcan
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($teachers as $teacher)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route('teachers.show', $teacher->id) }}" class="hover:text-brand-700">
                                        {{ $teacher->displayName() }} {{ $teacher->last_name }}
                                    </a>
                                    <x-badge :variant="$teacher->status->badgeVariant()" class="ml-1">{{ $teacher->status->label() }}</x-badge>
                                    @if ($teacher->user)
                                        <x-badge variant="gray" class="ml-1">{{ __('Has login') }}</x-badge>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500">
                                    <span class="font-mono">{{ $teacher->employee_number }}</span>
                                    · {{ trans_choice('{0} no active assignments|{1} :count active assignment|[2,*] :count active assignments', $teacher->active_assignments_count, ['count' => $teacher->active_assignments_count]) }}
                                </p>
                            </div>
                            <div class="shrink-0">
                                <x-button :href="route('teachers.show', $teacher->id)" size="sm" variant="secondary">{{ __('View') }}</x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $teachers->links() }}
        @endif
    </div>
</x-layouts.authenticated>
