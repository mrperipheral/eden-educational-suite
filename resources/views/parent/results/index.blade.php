<x-layouts.authenticated :title="__('Results — :name', ['name' => $student->fullName()])">
    <div class="space-y-6">
        @include('parent._child-nav', [
            'student' => $student, 'siblings' => $siblings, 'active' => 'results',
            'modules' => $modules, 'sectionRoute' => 'parent.results.index',
        ])

        @if (! $moduleOn)
            <x-empty-state
                :title="__('Results are not currently available')"
                :description="__('This school has not enabled results for this account yet.')"
            />
        @elseif ($results->isEmpty())
            <x-empty-state
                :title="__('No published results are available for this student yet')"
                :description="__('Results appear here once the school has published them.')"
            />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($results as $studentResult)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900">
                                    {{ $studentResult->resultRun->session?->name }} · {{ $studentResult->resultRun->period?->name }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ __('Average') }}: {{ rtrim(rtrim(number_format((float) $studentResult->average_percentage, 2), '0'), '.') }}%
                                    @if ($studentResult->overall_grade_code_snapshot)
                                        · {{ __('Grade') }}: {{ $studentResult->overall_grade_code_snapshot }}
                                    @endif
                                </p>
                            </div>
                            <x-button :href="route('parent.results.show', [$student->id, $studentResult->resultRun->id])" size="sm" variant="secondary">
                                {{ __('View') }}
                            </x-button>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>
</x-layouts.authenticated>
