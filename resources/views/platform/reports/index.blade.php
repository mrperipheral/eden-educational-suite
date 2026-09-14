<x-layouts.authenticated :title="__('Platform Reports')">
    <div class="space-y-6">
        <x-greeting :context="__('Eden Education Suite — platform-wide activity across every school.')" />

        <p class="text-sm"><a href="{{ route('admin.schools.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Schools') }}</a></p>

        <x-alert variant="info">
            {{ __('A platform-wide, aggregated overview only — no per-school financial or academic detail. Select a school to see its own detailed reports.') }}
        </x-alert>

        <x-card :title="__('School overview')">
            <div class="grid gap-4 sm:grid-cols-3">
                <div><p class="text-xs text-gray-500">{{ __('Total schools') }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $schoolOverview['total_schools'] }}</p></div>
                <div><p class="text-xs text-gray-500">{{ __('Active schools') }}</p><p class="mt-1 text-xl font-semibold text-green-700">{{ $schoolOverview['active_schools'] }}</p></div>
                <div><p class="text-xs text-gray-500">{{ __('Suspended schools') }}</p><p class="mt-1 text-xl font-semibold text-red-700">{{ $schoolOverview['suspended_schools'] }}</p></div>
            </div>

            <div class="mt-4 border-t border-gray-100 pt-4">
                <p class="text-xs font-medium text-gray-500">{{ __('Recently created schools') }}</p>
                @if ($schoolOverview['recent_schools']->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">{{ __('No schools yet.') }}</p>
                @else
                    <ul class="mt-2 space-y-1">
                        @foreach ($schoolOverview['recent_schools'] as $school)
                            <li class="text-sm text-gray-700">
                                <a href="{{ route('admin.schools.show', $school) }}" class="hover:text-brand-700">{{ $school->name }}</a>
                                <x-badge variant="gray" class="ml-1">{{ $school->status->label() }}</x-badge>
                                <span class="text-xs text-gray-400"> — {{ $school->created_at?->format('d M Y') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </x-card>

        <x-card :title="__('Platform usage')">
            <div class="grid gap-4 sm:grid-cols-3">
                <div><p class="text-xs text-gray-500">{{ __('Total users') }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $platformUsage['users'] }}</p></div>
                <div><p class="text-xs text-gray-500">{{ __('Students (all schools)') }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $platformUsage['students'] }}</p></div>
                <div><p class="text-xs text-gray-500">{{ __('Teachers (all schools)') }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $platformUsage['teachers'] }}</p></div>
            </div>

            <div class="mt-4 border-t border-gray-100 pt-4">
                <p class="text-xs font-medium text-gray-500">{{ __('Recent audit activity (all schools)') }}</p>
                @if ($platformUsage['recent_audit_activity']->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">{{ __('No audit activity yet.') }}</p>
                @else
                    <ul class="mt-2 space-y-1">
                        @foreach ($platformUsage['recent_audit_activity'] as $log)
                            <li class="text-sm text-gray-700">{{ $log->summary }} <span class="text-xs text-gray-400">— {{ $log->created_at?->diffForHumans() }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </x-card>

        <x-card :title="__('Module adoption')" :padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead><tr class="text-left text-xs font-medium text-gray-500"><th class="px-4 py-2">{{ __('Module') }}</th><th class="px-4 py-2">{{ __('Schools enabled') }}</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($moduleAdoption as $row)
                        <tr>
                            <td class="px-4 py-2">{{ $row['module']->label() }}</td>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-24 rounded-full bg-gray-100">
                                        <div class="h-2 rounded-full bg-brand-500" style="width: {{ $row['total_schools'] > 0 ? min(100, $row['enabled_schools'] / $row['total_schools'] * 100) : 0 }}%"></div>
                                    </div>
                                    <span>{{ $row['enabled_schools'] }} / {{ $row['total_schools'] }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </x-card>
    </div>
</x-layouts.authenticated>
