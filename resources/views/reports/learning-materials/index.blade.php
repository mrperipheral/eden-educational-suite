<x-layouts.authenticated :title="__('Learning Material Reports')">
    <x-slot:actions>
        @can('reports.export')
            <x-button :href="route('reports.learning-materials.export')" size="sm" variant="secondary">{{ __('Export CSV') }}</x-button>
        @endcan
    </x-slot:actions>

    <div class="space-y-6">
        <p class="text-sm"><a href="{{ route('reports.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Reports') }}</a></p>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-card><p class="text-xs text-gray-500">{{ __('Total materials') }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $summary['total'] }}</p></x-card>
            <x-card>
                <p class="text-xs text-gray-500">{{ __('By type') }}</p>
                <div class="mt-1 flex flex-wrap gap-3 text-sm">
                    @forelse ($summary['by_type'] as $type => $count)
                        <span>{{ ucfirst($type) }}: <strong>{{ $count }}</strong></span>
                    @empty
                        <span class="text-gray-400">{{ __('None yet') }}</span>
                    @endforelse
                </div>
            </x-card>
        </div>

        <x-card :title="__('By subject')" :padding="false">
            @if ($summary['by_subject']->isEmpty())
                <div class="p-4"><x-empty-state :title="__('No materials uploaded yet')" /></div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead><tr class="text-left text-xs font-medium text-gray-500"><th class="px-4 py-2">{{ __('Subject') }}</th><th class="px-4 py-2">{{ __('Materials') }}</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($summary['by_subject'] as $row)
                            <tr><td class="px-4 py-2">{{ $row->subject?->name }}</td><td class="px-4 py-2">{{ $row->total }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </x-card>

        <x-card :title="__('Recent uploads')" :padding="false">
            @if ($summary['recent']->isEmpty())
                <div class="p-4"><x-empty-state :title="__('No materials uploaded yet')" /></div>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($summary['recent'] as $material)
                        <li class="px-4 py-2 text-sm">
                            <span class="font-medium text-gray-900">{{ $material->title }}</span>
                            <span class="text-xs text-gray-500"> — {{ $material->subject?->name }} · {{ $material->level?->name }}{{ $material->arm ? ' — '.$material->arm->name : '' }} · {{ $material->uploadedBy?->name }} · {{ $material->created_at?->format('d M Y') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
