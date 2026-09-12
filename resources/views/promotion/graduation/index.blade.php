<x-layouts.authenticated :title="__('Graduation')">
    @can('graduation.manage')
        <x-slot:actions>
            <x-button :href="route('promotion.graduation.create')" size="sm">{{ __('Graduate students') }}</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-6">
        <p class="text-sm">
            <a href="{{ route('promotion.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Promotion') }}</a>
        </p>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        @if ($students->isEmpty())
            <x-empty-state
                :title="__('No graduated students yet')"
                :description="__('Students who have completed the school will appear here once graduated.')"
            >
                @can('graduation.manage')
                    <x-slot:actions>
                        <x-button :href="route('promotion.graduation.create')" size="sm">{{ __('Graduate students') }}</x-button>
                    </x-slot:actions>
                @endcan
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($students as $student)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route('students.show', $student->id) }}" class="hover:text-brand-700">
                                        {{ $student->displayName() }}
                                    </a>
                                    <x-badge variant="success" class="ml-1">{{ __('Graduated') }}</x-badge>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <span class="font-mono">{{ $student->admission_number }}</span>
                                    · {{ __('graduated :date', ['date' => $student->graduated_at?->format('d M Y')]) }}
                                    @if ($student->graduatedSession)
                                        ({{ $student->graduatedSession->name }})
                                    @endif
                                    @if ($student->graduatedBy)
                                        · {{ __('by :name', ['name' => $student->graduatedBy->name]) }}
                                    @endif
                                </p>
                                @if ($student->graduation_notes)
                                    <p class="mt-1 text-xs text-gray-500">{{ $student->graduation_notes }}</p>
                                @endif
                            </div>
                            @can('graduation.manage')
                                <div class="shrink-0">
                                    <x-confirm
                                        :action="route('promotion.graduation.reactivate', $student->id)"
                                        method="POST"
                                        size="sm"
                                        variant="secondary"
                                        :title="__('Reactivate student?')"
                                        :message="__('This reverses the graduation and returns the student to active status. Their historical enrollments and results are unaffected.')"
                                        :confirm="__('Reactivate')"
                                    >
                                        {{ __('Reactivate') }}
                                    </x-confirm>
                                </div>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $students->links() }}
        @endif
    </div>
</x-layouts.authenticated>
