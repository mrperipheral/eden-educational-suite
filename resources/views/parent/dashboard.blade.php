<x-layouts.authenticated :title="__('My Children')">
    <div class="space-y-6">
        <x-greeting :context="__('A quick look at your children today.')" />

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if (! $guardian)
            <x-empty-state
                :title="__('No students are currently linked to your account')"
                :description="__('Please contact your school administrator.')"
            />
        @elseif ($students->isEmpty())
            <x-empty-state
                :title="__('No students are currently linked to your account')"
                :description="__('Please contact your school administrator.')"
            />
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($students as $student)
                    <x-card>
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $student->fullName() }}</p>
                                <p class="font-mono text-xs text-gray-500">{{ $student->admission_number }}</p>
                            </div>
                            <x-badge :variant="$student->status->badgeVariant()">{{ $student->status->label() }}</x-badge>
                        </div>

                        <dl class="mt-3 space-y-1 text-xs text-gray-500">
                            @if ($student->currentEnrollment)
                                <div>
                                    {{ __('Class') }}:
                                    <span class="text-gray-900">
                                        {{ $student->currentEnrollment->level?->name }}
                                        @if ($student->currentEnrollment->arm) — {{ $student->currentEnrollment->arm->name }} @endif
                                    </span>
                                </div>
                                <div>{{ __('Session') }}: <span class="text-gray-900">{{ $student->currentEnrollment->session?->name }}</span></div>
                                @if ($student->currentEnrollment->period)
                                    <div>{{ __('Term') }}: <span class="text-gray-900">{{ $student->currentEnrollment->period->name }}</span></div>
                                @endif
                            @else
                                <div class="italic">{{ __('Not currently enrolled in a class.') }}</div>
                            @endif
                        </dl>

                        @if (isset($outstandingByStudent) || isset($latestResultByStudent))
                            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 border-t border-gray-100 pt-3 text-xs">
                                @if (isset($outstandingByStudent))
                                    @php($owed = $outstandingByStudent[$student->id] ?? '0.00')
                                    <span class="{{ bccomp($owed, '0.00', 2) === 1 ? 'font-medium text-red-700' : 'text-gray-500' }}">
                                        {{ __('Fees owed') }}: {{ $owed }}
                                    </span>
                                @endif
                                @if (isset($latestResultByStudent) && ($result = $latestResultByStudent[$student->id] ?? null))
                                    <span class="text-gray-500">
                                        {{ __('Latest result') }}: {{ $result->resultRun?->period?->name ?? $result->resultRun?->session?->name }}
                                    </span>
                                @endif
                            </div>
                        @endif

                        <div class="mt-4">
                            <x-button :href="route('parent.children.show', $student->id)" size="sm" variant="secondary">{{ __('View profile') }}</x-button>
                        </div>
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.authenticated>
