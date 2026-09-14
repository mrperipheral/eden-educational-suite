@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $tabs = ['batches' => __('Promotion batches'), 'graduation' => __('Graduation history')];
@endphp

<x-layouts.authenticated :title="__('Promotion & Graduation Reports')">
    <x-slot:actions>
        @can('reports.export')
            <x-button :href="route('reports.promotion.export', array_merge($filters, ['tab' => $tab]))" size="sm" variant="secondary">{{ __('Export CSV') }}</x-button>
        @endcan
    </x-slot:actions>

    <div class="space-y-6">
        <p class="text-sm"><a href="{{ route('reports.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Reports') }}</a></p>

        <x-card><p class="text-xs text-gray-500">{{ __('Total graduated students') }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $graduatedCount }}</p></x-card>

        <nav class="flex flex-wrap gap-2 border-b border-gray-200 pb-2">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('reports.promotion.index', array_merge($filters, ['tab' => $key])) }}"
                    @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-brand-50 text-brand-700' => $tab === $key, 'text-gray-600 hover:bg-gray-100' => $tab !== $key])>
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('reports.promotion.index') }}" class="flex flex-wrap items-end gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="space-y-1">
                <label for="session" class="block text-xs font-medium text-gray-700">{{ __('Session') }}</label>
                <select id="session" name="session" class="{{ $selectClass }}">
                    <option value="">{{ __('All sessions') }}</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected(($filters['session'] ?? null) == $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" size="sm" variant="secondary">{{ __('Filter') }}</x-button>
            @if (array_filter($filters))
                <x-button :href="route('reports.promotion.index', ['tab' => $tab])" size="sm" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        @if ($tab === 'graduation')
            @if ($graduationHistory->isEmpty())
                <x-empty-state :title="__('No graduation records match')" />
            @else
                <x-card :padding="false">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead><tr class="text-left text-xs font-medium text-gray-500">
                            <th class="px-4 py-2">{{ __('Student') }}</th><th class="px-4 py-2">{{ __('Admission #') }}</th>
                            <th class="px-4 py-2">{{ __('Graduated on') }}</th><th class="px-4 py-2">{{ __('Session') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($graduationHistory as $student)
                                <tr>
                                    <td class="px-4 py-2">{{ $student->fullName() }}</td>
                                    <td class="px-4 py-2">{{ $student->admission_number }}</td>
                                    <td class="px-4 py-2">{{ $student->graduated_at?->format('d M Y') }}</td>
                                    <td class="px-4 py-2">{{ $student->graduatedSession?->name }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </x-card>
                {{ $graduationHistory->links() }}
            @endif
        @else
            @if ($batches->isEmpty())
                <x-empty-state :title="__('No promotion batches match')" />
            @else
                <x-card :padding="false">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead><tr class="text-left text-xs font-medium text-gray-500">
                            <th class="px-4 py-2">{{ __('Source') }}</th><th class="px-4 py-2">{{ __('Target') }}</th>
                            <th class="px-4 py-2">{{ __('Status') }}</th><th class="px-4 py-2">{{ __('Students') }}</th>
                            <th class="px-4 py-2">{{ __('Created') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($batches as $batch)
                                <tr>
                                    <td class="px-4 py-2">{{ $batch->sourceLevel?->name }}{{ $batch->sourceArm ? ' — '.$batch->sourceArm->name : '' }} ({{ $batch->sourceSession?->name }})</td>
                                    <td class="px-4 py-2">{{ $batch->targetLevel?->name }}{{ $batch->targetArm ? ' — '.$batch->targetArm->name : '' }} ({{ $batch->targetSession?->name }})</td>
                                    <td class="px-4 py-2"><x-badge :variant="$batch->status->badgeVariant()">{{ $batch->status->label() }}</x-badge></td>
                                    <td class="px-4 py-2">{{ $batch->records_count }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ $batch->created_at?->format('d M Y') }} · {{ $batch->createdBy?->name }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </x-card>
                {{ $batches->links() }}
            @endif
        @endif
    </div>
</x-layouts.authenticated>
