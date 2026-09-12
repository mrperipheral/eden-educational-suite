@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<x-layouts.authenticated :title="__('Examinations')">
    @can('cbt.author')
        <x-slot:actions>
            <x-button :href="route('cbt.questions.index')" size="sm" variant="secondary">{{ __('Question bank') }}</x-button>
            <x-button :href="route('cbt.examinations.create')" size="sm">{{ __('Create examination') }}</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        <form method="GET" action="{{ route('cbt.examinations.index') }}" class="flex flex-wrap items-end gap-2">
            <div class="space-y-1">
                <label for="level" class="block text-xs font-medium text-gray-700">{{ __('Level') }}</label>
                <select id="level" name="level" class="{{ $selectClass }}" onchange="this.form.submit()">
                    <option value="">{{ __('All levels') }}</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->id }}" @selected(($filters['level'] ?? null) == $level->id)>{{ $level->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="status" class="block text-xs font-medium text-gray-700">{{ __('Status') }}</label>
                <select id="status" name="status" class="{{ $selectClass }}" onchange="this.form.submit()">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Enums\ExaminationStatus::all() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        @if ($examinations->isEmpty())
            <x-empty-state
                :title="__('No examinations yet')"
                :description="__('Create an examination, attach multiple-choice/true-false questions, then schedule it to make it available to its class.')"
            >
                @can('cbt.author')
                    <x-slot:actions>
                        <x-button :href="route('cbt.examinations.create')" size="sm">{{ __('Create examination') }}</x-button>
                    </x-slot:actions>
                @endcan
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($examinations as $examination)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route('cbt.examinations.show', $examination->id) }}" class="hover:text-brand-700">{{ $examination->title }}</a>
                                    <x-badge :variant="$examination->status->badgeVariant()" class="ml-1">{{ $examination->status->label() }}</x-badge>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $examination->subject?->name }} ·
                                    {{ $examination->level?->name }}{{ $examination->arm ? ' — '.$examination->arm->name : '' }}
                                    ({{ $examination->session?->name }})
                                    · {{ __(':count questions', ['count' => $examination->questions_count]) }}
                                    · {{ $examination->starts_at->format('d M Y, H:i') }}
                                </p>
                            </div>
                            <div class="shrink-0">
                                <x-button :href="route('cbt.examinations.show', $examination->id)" size="sm" variant="secondary">{{ __('View') }}</x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $examinations->links() }}
        @endif
    </div>
</x-layouts.authenticated>
