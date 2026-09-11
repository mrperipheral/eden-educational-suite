<x-layouts.authenticated :title="__('Results')">
    <div class="space-y-6">
        @include('student._nav', ['active' => 'results'])

        @if (! $student)
            <x-empty-state :title="__('Your account is not linked to a student record yet')" :description="__('Please contact your school administrator.')" />
        @elseif (! $moduleOn)
            <x-empty-state :title="__('Results are not currently available')" :description="__('This school has not enabled results for this account yet.')" />
        @elseif ($results->isEmpty())
            <x-empty-state :title="__('No published results are available yet')" :description="__('Results appear here once the school has published them.')" />
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
                            <x-button :href="route('student.results.show', $studentResult->resultRun->id)" size="sm" variant="secondary">
                                {{ __('View') }}
                            </x-button>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>
</x-layouts.authenticated>
