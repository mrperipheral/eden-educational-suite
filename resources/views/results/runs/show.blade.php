@php
    $status = $run->status;
    $fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp

<x-layouts.authenticated :title="__(':class — :term', ['class' => $run->level?->name.' '.$run->arm?->name, 'term' => $run->period?->name])">
    <x-slot:actions>
        <div class="flex flex-wrap gap-2">
            @if ($canManage && $run->recompilable())
                <form method="POST" action="{{ route('results.runs.compile', $run->id) }}">@csrf
                    <x-button type="submit" size="sm">{{ $run->isDraft() ? __('Compile') : __('Recompile') }}</x-button>
                </form>
            @endif
            @if ($canManage && $run->isCompiled())
                <form method="POST" action="{{ route('results.runs.review', $run->id) }}">@csrf
                    <x-button type="submit" size="sm" variant="secondary">{{ __('Mark reviewed') }}</x-button>
                </form>
            @endif
            @if ($canPublish && $run->isReviewed())
                <x-confirm :action="route('results.runs.approve', $run->id)" method="POST" size="sm" variant="secondary"
                    :confirm="__('Approve')" :title="__('Approve this run?')"
                    :message="__('Numbers are frozen from this point — further corrections need the adjustment workflow.')">
                    {{ __('Approve') }}
                </x-confirm>
            @endif
            @if ($canPublish && $run->isApproved())
                <form method="POST" action="{{ route('results.runs.publish', $run->id) }}">@csrf
                    <x-button type="submit" size="sm">{{ __('Publish') }}</x-button>
                </form>
            @endif
            @if ($canPublish && $run->isPublished())
                <x-confirm :action="route('results.runs.lock', $run->id)" method="POST" size="sm"
                    :confirm="__('Lock')" :title="__('Lock this run?')"
                    :message="__('This is the historical, reproducible state. Corrections after this need the adjustment workflow.')">
                    {{ __('Lock') }}
                </x-confirm>
            @endif
            @if ($canManage && $studentResults->isEmpty())
                <x-confirm :action="route('results.runs.destroy', $run->id)" method="DELETE" size="sm" variant="ghost"
                    :confirm="__('Delete')" :title="__('Delete this result run?')">
                    {{ __('Delete') }}
                </x-confirm>
            @endif
        </div>
    </x-slot:actions>

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif
        @if ($errors->has('compilation'))
            <x-alert variant="danger" :title="__('Compilation was blocked')">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->get('compilation') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <p class="text-sm">
            <a href="{{ route('results.runs.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Result runs') }}</a>
        </p>

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-sm font-medium text-gray-900">{{ $run->level?->name }} — {{ $run->arm?->name }}</p>
                <x-badge :variant="$status->badgeVariant()">{{ $status->label() }}</x-badge>
                @unless ($run->ranking_enabled) <x-badge variant="gray">{{ __('Ranking off') }}</x-badge> @endunless
            </div>
            <p class="mt-1 text-xs text-gray-500">
                {{ $run->session?->name }} · {{ $run->period?->name }} ·
                {{ __('grading: :g · weighting: :w', ['g' => $run->gradingScheme?->name, 'w' => $run->weightingScheme?->name]) }}
            </p>
            <p class="mt-2 text-xs text-gray-400">
                @if ($run->compiledBy) {{ __('compiled by :name', ['name' => $run->compiledBy->name]) }} @endif
                @if ($run->publishedBy) · {{ __('published by :name', ['name' => $run->publishedBy->name]) }} @endif
                @if ($run->lockedBy) · {{ __('locked by :name', ['name' => $run->lockedBy->name]) }} @endif
            </p>
        </x-card>

        <x-card :title="__('Students')">
            @if ($studentResults->isEmpty())
                <x-empty-state :title="__('Not compiled yet')"
                    :description="__('Compile this run once every subject taught this term is locked and fully scored.')" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-gray-500">
                                <th class="py-2 pr-4">{{ __('Student') }}</th>
                                <th class="py-2 pr-4">{{ __('Total') }}</th>
                                <th class="py-2 pr-4">{{ __('Average') }}</th>
                                <th class="py-2 pr-4">{{ __('Grade') }}</th>
                                @if ($run->ranking_enabled)
                                    <th class="py-2 pr-4">{{ __('Position') }}</th>
                                @endif
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($studentResults as $result)
                                <tr x-data="{ open: false }">
                                    <td class="py-2 pr-4">
                                        <button type="button" x-on:click="open = !open" class="text-left font-medium text-gray-900 hover:text-brand-700">
                                            {{ $result->student?->displayName() }} {{ $result->student?->last_name }}
                                        </button>
                                        <p class="font-mono text-xs text-gray-400">{{ $result->student?->admission_number }}</p>
                                    </td>
                                    <td class="py-2 pr-4">{{ $fmt($result->total_percentage) }}</td>
                                    <td class="py-2 pr-4">{{ $fmt($result->average_percentage) }}%</td>
                                    <td class="py-2 pr-4">{{ $result->overall_grade_code_snapshot ?? '—' }}</td>
                                    @if ($run->ranking_enabled)
                                        <td class="py-2 pr-4">{{ $result->position ? __(':n of :size', ['n' => $result->position, 'size' => $result->class_size]) : '—' }}</td>
                                    @endif
                                    <td class="py-2">
                                        <a href="{{ route('results.runs.students.report-card', [$run->id, $result->id]) }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">{{ __('Report card') }}</a>
                                    </td>
                                </tr>
                                <tr x-show="open" x-cloak>
                                    <td colspan="6" class="bg-gray-50 px-4 py-4">
                                        @include('results.runs._student-detail', ['result' => $result])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
