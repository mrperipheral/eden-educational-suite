@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<x-layouts.authenticated :title="__('Student Reports')">
    <x-slot:actions>
        @can('reports.export')
            <x-button :href="route('reports.students.export', $filters)" size="sm" variant="secondary">{{ __('Export CSV') }}</x-button>
        @endcan
    </x-slot:actions>

    <div class="space-y-6">
        <p class="text-sm"><a href="{{ route('reports.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Reports') }}</a></p>

        <form method="GET" action="{{ route('reports.students.index') }}" class="flex flex-wrap items-end gap-2" x-data="{ levelId: '{{ $filters['level'] ?? '' }}' }">
            <div class="space-y-1">
                <label for="level" class="block text-xs font-medium text-gray-700">{{ __('Level') }}</label>
                <select id="level" name="level" x-model="levelId" class="{{ $selectClass }}">
                    <option value="">{{ __('All levels') }}</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->id }}">{{ $level->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="arm" class="block text-xs font-medium text-gray-700">{{ __('Arm') }}</label>
                <select id="arm" name="arm" class="{{ $selectClass }}">
                    <option value="">{{ __('All arms') }}</option>
                    @foreach ($levels as $level)
                        @foreach ($level->arms as $arm)
                            <option value="{{ $arm->id }}" x-show="levelId === '{{ $level->id }}'" @selected(($filters['arm'] ?? null) == $arm->id)>{{ $level->name }} — {{ $arm->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <x-button type="submit" size="sm" variant="secondary">{{ __('Filter') }}</x-button>
            @if (array_filter($filters))
                <x-button :href="route('reports.students.index')" size="sm" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-card><p class="text-xs text-gray-500">{{ __('Total students') }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $summary['total'] }}</p></x-card>
            @foreach (['active' => __('Active'), 'inactive' => __('Inactive'), 'withdrawn' => __('Withdrawn'), 'graduated' => __('Graduated')] as $key => $label)
                <x-card><p class="text-xs text-gray-500">{{ $label }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $summary['by_status'][$key] ?? 0 }}</p></x-card>
            @endforeach
        </div>

        @if (! empty($summary['by_gender']))
            <x-card :title="__('By gender')">
                <div class="flex flex-wrap gap-4 text-sm">
                    @foreach ($summary['by_gender'] as $gender => $count)
                        <span class="text-gray-700">{{ ucfirst($gender) }}: <strong>{{ $count }}</strong></span>
                    @endforeach
                </div>
            </x-card>
        @endif

        <x-card :title="__('By level')" :padding="false">
            @if ($summary['by_level']->isEmpty())
                <div class="p-4"><x-empty-state :title="__('No enrollment data yet')" /></div>
            @else
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead><tr class="text-left text-xs font-medium text-gray-500"><th class="px-4 py-2">{{ __('Level') }}</th><th class="px-4 py-2">{{ __('Students') }}</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($summary['by_level'] as $row)
                            <tr><td class="px-4 py-2">{{ $row->level?->name }}</td><td class="px-4 py-2">{{ $row->total }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <x-card :title="__('By arm')" :padding="false">
            @if ($summary['by_arm']->isEmpty())
                <div class="p-4"><x-empty-state :title="__('No arm-level enrollment data yet')" /></div>
            @else
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead><tr class="text-left text-xs font-medium text-gray-500"><th class="px-4 py-2">{{ __('Level') }}</th><th class="px-4 py-2">{{ __('Arm') }}</th><th class="px-4 py-2">{{ __('Students') }}</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($summary['by_arm'] as $row)
                            <tr><td class="px-4 py-2">{{ $row->level?->name }}</td><td class="px-4 py-2">{{ $row->arm?->name }}</td><td class="px-4 py-2">{{ $row->total }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
