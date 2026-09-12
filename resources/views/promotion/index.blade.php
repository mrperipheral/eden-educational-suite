<x-layouts.authenticated :title="__('Promotion')">
    @can('promotion.manage')
        <x-slot:actions>
            <x-button :href="route('promotion.create')" size="sm">{{ __('Promote students') }}</x-button>
            <x-button :href="route('promotion.graduation.index')" size="sm" variant="secondary">{{ __('Graduation') }}</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if ($batches->isEmpty())
            <x-empty-state
                :title="__('No promotions yet')"
                :description="__('Promote a class to a new session to see the history here.')"
            >
                @can('promotion.manage')
                    <x-slot:actions>
                        <x-button :href="route('promotion.create')" size="sm">{{ __('Promote students') }}</x-button>
                    </x-slot:actions>
                @endcan
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($batches as $batch)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route('promotion.show', $batch->id) }}" class="hover:text-brand-700">
                                        {{ $batch->sourceLevel?->name }}{{ $batch->sourceArm ? ' — '.$batch->sourceArm->name : '' }}
                                        → {{ $batch->targetLevel?->name }}{{ $batch->targetArm ? ' — '.$batch->targetArm->name : '' }}
                                    </a>
                                    <x-badge :variant="$batch->status->badgeVariant()" class="ml-1">{{ $batch->status->label() }}</x-badge>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $batch->sourceSession?->name }} → {{ $batch->targetSession?->name }}
                                    · {{ __(':count promoted', ['count' => $batch->promoted_count]) }}
                                    · {{ __('by :name on :date', ['name' => $batch->createdBy?->name ?? __('Unknown'), 'date' => $batch->created_at->format('d M Y')]) }}
                                </p>
                            </div>
                            <div class="shrink-0">
                                <x-button :href="route('promotion.show', $batch->id)" size="sm" variant="secondary">{{ __('View') }}</x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $batches->links() }}
        @endif
    </div>
</x-layouts.authenticated>
