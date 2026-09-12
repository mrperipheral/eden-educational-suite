<x-layouts.authenticated :title="$assessment->candidate_name">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('entry-assessments.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Entry / Placement Assessment') }}</a>
        </p>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <x-badge :variant="$assessment->status->badgeVariant()">{{ $assessment->status->label() }}</x-badge>
                @if ($assessment->result)
                    <x-badge variant="gray">{{ $assessment->result }}</x-badge>
                @endif
            </div>

            <h2 class="mt-3 text-lg font-semibold text-gray-900">{{ $assessment->candidate_name }}</h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ $assessment->subject?->name }}
                · {{ $assessment->level?->name }}{{ $assessment->arm ? ' — '.$assessment->arm->name : __(' (arm not yet decided)') }}
                · {{ __('Assessed :date', ['date' => $assessment->assessed_on->format('d M Y')]) }}
            </p>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium text-gray-500">{{ __('Application / admission reference') }}</dt>
                    <dd class="text-sm text-gray-900">{{ $assessment->admission_reference ?: __('—') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500">{{ __('Linked student record') }}</dt>
                    <dd class="text-sm text-gray-900">
                        {{ $assessment->student ? trim($assessment->student->first_name.' '.$assessment->student->last_name).' ('.$assessment->student->admission_number.')' : __('Not linked — a prospective candidate') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500">{{ __('Score') }}</dt>
                    <dd class="text-sm text-gray-900">
                        @if ($assessment->score !== null)
                            {{ $assessment->score }} / {{ $assessment->max_score }} ({{ $assessment->percentage() }}%)
                        @else
                            {{ __('Not yet entered') }} ({{ __('max :max', ['max' => $assessment->max_score]) }})
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500">{{ __('Assessor') }}</dt>
                    <dd class="text-sm text-gray-900">{{ $assessment->assessor?->name ?? __('—') }}</dd>
                </div>
            </dl>

            @if ($assessment->notes)
                <div class="mt-4">
                    <dt class="text-xs font-medium text-gray-500">{{ __('Notes / comments') }}</dt>
                    <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $assessment->notes }}</dd>
                </div>
            @endif

            @can('placement.record')
                <div class="mt-6 flex flex-wrap gap-2">
                    <x-button :href="route('entry-assessments.edit', $assessment->id)" size="sm" variant="secondary">{{ __('Edit') }}</x-button>
                    @if ($assessment->status === \App\Enums\EntryAssessmentStatus::Active)
                        <x-confirm
                            :action="route('entry-assessments.archive', $assessment->id)"
                            method="POST"
                            size="sm"
                            :title="__('Archive this record?')"
                            :message="__('It moves out of the active working set. Nothing is deleted — it can be restored later.')"
                            :confirm="__('Archive')"
                        >
                            {{ __('Archive') }}
                        </x-confirm>
                    @else
                        <form method="POST" action="{{ route('entry-assessments.restore', $assessment->id) }}">
                            @csrf
                            <x-button type="submit" size="sm" variant="ghost">{{ __('Restore') }}</x-button>
                        </form>
                    @endif
                </div>
            @endcan
        </x-card>
    </div>
</x-layouts.authenticated>
