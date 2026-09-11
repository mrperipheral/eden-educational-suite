@php
    $selectClass = 'rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $filtering = collect($filters)->filter(fn ($v) => $v !== null)->isNotEmpty();
@endphp

<x-layouts.authenticated :title="__('Result runs')">
    <x-slot:actions>
        <div class="flex flex-wrap gap-2">
            @can('result.manage')
                <x-button :href="route('results.grading-schemes.index')" size="sm" variant="secondary">{{ __('Grading schemes') }}</x-button>
                <x-button :href="route('results.weighting-schemes.index')" size="sm" variant="secondary">{{ __('Weighting schemes') }}</x-button>
            @endcan
            @can('result.view')
                <x-button :href="route('results.report-card-configuration.edit')" size="sm" variant="secondary">{{ __('Report card') }}</x-button>
            @endcan
            @can('result.manage')
                <x-button :href="route('results.runs.create')" size="sm">{{ __('New result run') }}</x-button>
            @endcan
        </div>
    </x-slot:actions>

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        <form method="GET" action="{{ route('results.runs.index') }}"
            x-data="{ levelId: '{{ $filters['level'] }}' }" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Session') }}</label>
                <select name="session" class="{{ $selectClass }}">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($sessions as $s)
                        <option value="{{ $s->id }}" @selected($filters['session'] === $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Level') }}</label>
                <select name="level" x-model="levelId" class="{{ $selectClass }}">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($levels as $l)
                        <option value="{{ $l->id }}" @selected($filters['level'] === $l->id)>{{ $l->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Class') }}</label>
                <select name="arm" class="{{ $selectClass }}">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($levels as $l)
                        @foreach ($l->arms as $arm)
                            <option value="{{ $arm->id }}" x-show="!levelId || levelId === '{{ $l->id }}'" @selected($filters['arm'] === $arm->id)>{{ $l->name }} — {{ $arm->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Status') }}</label>
                <select name="status" class="{{ $selectClass }}">
                    <option value="">{{ __('Any') }}</option>
                    @foreach (\App\Enums\ResultRunStatus::all() as $s)
                        <option value="{{ $s->value }}" @selected($filters['status'] === $s)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" variant="secondary">{{ __('Filter') }}</x-button>
            @if ($filtering)
                <x-button :href="route('results.runs.index')" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        @if ($runs->isEmpty())
            <x-empty-state
                :title="$filtering ? __('No result runs match') : __('No result runs yet')"
                :description="$filtering ? __('Try a different filter.') : __('Create a result run for a class and term, then compile it once every subject is locked and scored.')"
            >
                @can('result.manage')
                    @unless ($filtering)
                        <x-slot:actions>
                            <x-button :href="route('results.runs.create')" size="sm">{{ __('New result run') }}</x-button>
                        </x-slot:actions>
                    @endunless
                @endcan
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($runs as $run)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route('results.runs.show', $run->id) }}" class="hover:text-brand-700">{{ $run->level?->name }} — {{ $run->arm?->name }}</a>
                                    <x-badge :variant="$run->status->badgeVariant()" class="ml-1">{{ $run->status->label() }}</x-badge>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $run->session?->name }} · {{ $run->period?->name }} ·
                                    {{ trans_choice('{0}not compiled|{1}:count student|[2,*]:count students', $run->student_results_count, ['count' => $run->student_results_count]) }}
                                </p>
                            </div>
                            <x-button :href="route('results.runs.show', $run->id)" size="sm" variant="secondary">{{ __('Open') }}</x-button>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $runs->links() }}
        @endif
    </div>
</x-layouts.authenticated>
