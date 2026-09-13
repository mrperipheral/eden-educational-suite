<x-layouts.authenticated :title="__('Staff Reports')">
    <x-slot:actions>
        @can('reports.export')
            <x-button :href="route('reports.staff.export')" size="sm" variant="secondary">{{ __('Export CSV') }}</x-button>
        @endcan
    </x-slot:actions>

    <div class="space-y-6">
        <p class="text-sm"><a href="{{ route('reports.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Reports') }}</a></p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-card><p class="text-xs text-gray-500">{{ __('Total teachers') }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $summary['total'] }}</p></x-card>
            @foreach (['active' => __('Active'), 'inactive' => __('Inactive'), 'suspended' => __('Suspended'), 'resigned' => __('Resigned')] as $key => $label)
                <x-card><p class="text-xs text-gray-500">{{ $label }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $summary['by_status'][$key] ?? 0 }}</p></x-card>
            @endforeach
        </div>

        <x-card :title="__('Active assignments by subject')" :padding="false">
            @if ($summary['by_subject']->isEmpty())
                <div class="p-4"><x-empty-state :title="__('No active teaching assignments yet')" /></div>
            @else
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead><tr class="text-left text-xs font-medium text-gray-500"><th class="px-4 py-2">{{ __('Subject') }}</th><th class="px-4 py-2">{{ __('Active assignments') }}</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($summary['by_subject'] as $row)
                            <tr><td class="px-4 py-2">{{ $row->subject?->name }}</td><td class="px-4 py-2">{{ $row->total }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <x-card :title="__('Assignment workload (top 20)')" :padding="false">
            @if ($summary['workload']->isEmpty())
                <div class="p-4"><x-empty-state :title="__('No active teaching assignments yet')" /></div>
            @else
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead><tr class="text-left text-xs font-medium text-gray-500"><th class="px-4 py-2">{{ __('Teacher') }}</th><th class="px-4 py-2">{{ __('Active assignments') }}</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($summary['workload'] as $row)
                            <tr><td class="px-4 py-2">{{ $row->teacher?->fullName() }}</td><td class="px-4 py-2">{{ $row->assignment_count }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
