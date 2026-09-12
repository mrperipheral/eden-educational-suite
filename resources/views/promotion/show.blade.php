<x-layouts.authenticated :title="__('Promotion batch')">
    <div class="space-y-6">
        <p class="text-sm">
            <a href="{{ route('promotion.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Promotion') }}</a>
        </p>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-sm font-medium text-gray-900">
                        {{ $batch->sourceLevel?->name }}{{ $batch->sourceArm ? ' — '.$batch->sourceArm->name : '' }}
                        ({{ $batch->sourceSession?->name }}{{ $batch->sourcePeriod ? ', '.$batch->sourcePeriod->name : '' }})
                        → {{ $batch->targetLevel?->name }}{{ $batch->targetArm ? ' — '.$batch->targetArm->name : '' }}
                        ({{ $batch->targetSession?->name }})
                    </p>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ __('by :name on :date', ['name' => $batch->createdBy?->name ?? __('Unknown'), 'date' => $batch->created_at->format('d M Y, H:i')]) }}
                    </p>
                </div>
                <x-badge :variant="$batch->status->badgeVariant()">{{ $batch->status->label() }}</x-badge>
            </div>

            @if ($batch->notes)
                <p class="mt-3 text-sm text-gray-600">{{ $batch->notes }}</p>
            @endif

            <dl class="mt-4 grid grid-cols-3 gap-4 border-t border-gray-100 pt-4 text-center sm:max-w-sm">
                <div>
                    <dt class="text-xs text-gray-500">{{ __('Promoted') }}</dt>
                    <dd class="text-lg font-semibold text-gray-900">{{ $batch->records->where('status', \App\Enums\PromotionRecordStatus::Promoted)->count() }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('Skipped') }}</dt>
                    <dd class="text-lg font-semibold text-gray-900">{{ $batch->records->where('status', \App\Enums\PromotionRecordStatus::Skipped)->count() }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('Failed') }}</dt>
                    <dd class="text-lg font-semibold text-gray-900">{{ $batch->records->where('status', \App\Enums\PromotionRecordStatus::Failed)->count() }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card :title="__('Students')" :padding="false">
            <ul class="divide-y divide-gray-100">
                @foreach ($batch->records as $record)
                    <li class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900">
                                {{ $record->student?->displayName() ?? __('Unknown student') }}
                            </p>
                            @if ($record->status === \App\Enums\PromotionRecordStatus::Failed && $record->failure_reason)
                                <p class="text-xs text-danger-600">{{ $record->failure_reason }}</p>
                            @endif
                        </div>
                        <x-badge :variant="$record->status->badgeVariant()">{{ $record->status->label() }}</x-badge>
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>
</x-layouts.authenticated>
