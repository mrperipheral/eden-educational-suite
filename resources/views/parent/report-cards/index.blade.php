<x-layouts.authenticated :title="__('Report cards — :name', ['name' => $student->fullName()])">
    <div class="space-y-6">
        @include('parent._child-nav', [
            'student' => $student, 'siblings' => $siblings, 'active' => 'report-cards',
            'modules' => $modules, 'sectionRoute' => 'parent.report-cards.index',
        ])

        @if (! $moduleOn)
            <x-empty-state
                :title="__('Report cards are not currently available')"
                :description="__('This school has not enabled results for this account yet.')"
            />
        @elseif ($results->isEmpty())
            <x-empty-state
                :title="__('No report card has been published for this student yet')"
                :description="__('A report card appears here once the school has published it.')"
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
                                <x-badge :variant="$studentResult->resultRun->status->badgeVariant()">{{ $studentResult->resultRun->status->label() }}</x-badge>
                            </div>
                            <x-button :href="route('parent.report-cards.show', [$student->id, $studentResult->resultRun->id])" size="sm" variant="secondary">
                                {{ __('View report card') }}
                            </x-button>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>
</x-layouts.authenticated>
