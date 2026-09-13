<x-layouts.authenticated :title="__('Communication Reports')">
    <x-slot:actions>
        @can('reports.export')
            <x-button :href="route('reports.communication.export')" size="sm" variant="secondary">{{ __('Export CSV') }}</x-button>
        @endcan
    </x-slot:actions>

    <div class="space-y-6">
        <p class="text-sm"><a href="{{ route('reports.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Reports') }}</a></p>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-card><p class="text-xs text-gray-500">{{ __('Total threads') }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $summary['threads_total'] }}</p></x-card>
            <x-card><p class="text-xs text-gray-500">{{ __('Published announcements') }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $summary['announcements_published'] }}</p></x-card>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-card>
                <p class="text-xs text-gray-500">{{ __('Threads by status') }}</p>
                <div class="mt-1 flex flex-wrap gap-3 text-sm">
                    @forelse ($summary['threads_by_status'] as $status => $count)
                        <span>{{ ucfirst($status) }}: <strong>{{ $count }}</strong></span>
                    @empty
                        <span class="text-gray-400">{{ __('None yet') }}</span>
                    @endforelse
                </div>
            </x-card>
            <x-card>
                <p class="text-xs text-gray-500">{{ __('Threads by category') }}</p>
                <div class="mt-1 flex flex-wrap gap-3 text-sm">
                    @forelse ($summary['threads_by_category'] as $category => $count)
                        <span>{{ ucfirst($category) }}: <strong>{{ $count }}</strong></span>
                    @empty
                        <span class="text-gray-400">{{ __('None yet') }}</span>
                    @endforelse
                </div>
            </x-card>
        </div>

        <x-card :title="__('Recent threads')" :padding="false">
            @if ($summary['recent_threads']->isEmpty())
                <div class="p-4"><x-empty-state :title="__('No communication threads yet')" /></div>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($summary['recent_threads'] as $thread)
                        <li class="px-4 py-2 text-sm">
                            <x-badge :variant="$thread->status->badgeVariant()">{{ $thread->status->label() }}</x-badge>
                            <span class="ml-1 font-medium text-gray-900">{{ $thread->subject }}</span>
                            <span class="text-xs text-gray-500"> — {{ $thread->student?->fullName() ?? __('General') }} · {{ $thread->assignedTo?->name ?? __('Unassigned') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
