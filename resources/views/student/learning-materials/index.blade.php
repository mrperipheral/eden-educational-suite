<x-layouts.authenticated :title="__('Learning Materials')">
    <div class="space-y-6">
        @include('student._nav', ['active' => 'learning-materials'])

        @if (! $student)
            <x-empty-state :title="__('Your account is not linked to a student record yet')" :description="__('Please contact your school administrator.')" />
        @elseif (! $moduleOn)
            <x-empty-state :title="__('Learning materials are not currently available')" :description="__('This school has not enabled learning materials for this account yet.')" />
        @elseif (is_null($materials))
            <x-empty-state :title="__('No current class placement')" :description="__('Materials appear here once you are enrolled in a class.')" />
        @elseif ($materials->isEmpty())
            <x-empty-state :title="__('No materials yet')" :description="__('Materials your teachers upload for your class will appear here.')" />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($materials as $material)
                        <li class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    {{ $material->title }}
                                    <x-badge variant="gray" class="ml-1">{{ $material->type->label() }}</x-badge>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $material->subject?->name }}
                                    · {{ $material->created_at->format('d M Y') }}
                                </p>
                                @if ($material->description)
                                    <p class="mt-1 text-xs text-gray-500">{{ $material->description }}</p>
                                @endif
                            </div>
                            <div class="shrink-0">
                                <x-button :href="route('student.learning-materials.download', $material->id)" size="sm" variant="secondary">{{ __('Download') }}</x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $materials->links() }}
        @endif
    </div>
</x-layouts.authenticated>
