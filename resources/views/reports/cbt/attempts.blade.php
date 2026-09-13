<x-layouts.authenticated :title="$examination->title">
    <div class="space-y-6">
        <p class="text-sm"><a href="{{ route('reports.cbt.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← CBT Reports') }}</a></p>

        <x-card>
            <h2 class="text-sm font-semibold text-gray-900">{{ $examination->title }}</h2>
            <p class="mt-1 text-xs text-gray-500">
                {{ $examination->session?->name }} · {{ $examination->level?->name }}{{ $examination->arm ? ' — '.$examination->arm->name : '' }} · {{ $examination->subject?->name }}
            </p>
        </x-card>

        @if ($attempts->isEmpty())
            <x-empty-state :title="__('No attempts yet')" />
        @else
            <x-card :padding="false">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead><tr class="text-left text-xs font-medium text-gray-500">
                        <th class="px-4 py-2">{{ __('Student') }}</th><th class="px-4 py-2">{{ __('Status') }}</th>
                        <th class="px-4 py-2">{{ __('Score') }}</th><th class="px-4 py-2">{{ __('Percentage') }}</th>
                        <th class="px-4 py-2">{{ __('Passed') }}</th><th class="px-4 py-2">{{ __('Submitted') }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($attempts as $attempt)
                            <tr>
                                <td class="px-4 py-2">{{ $attempt->student?->fullName() }}</td>
                                <td class="px-4 py-2"><x-badge :variant="$attempt->status->badgeVariant()">{{ $attempt->status->label() }}</x-badge></td>
                                <td class="px-4 py-2">{{ $attempt->score !== null ? $attempt->score.' / '.$attempt->max_score : '—' }}</td>
                                <td class="px-4 py-2">{{ $attempt->percentage ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    @if ($attempt->passed === null)
                                        —
                                    @elseif ($attempt->passed)
                                        <x-badge variant="success">{{ __('Yes') }}</x-badge>
                                    @else
                                        <x-badge variant="danger">{{ __('No') }}</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ $attempt->submitted_at?->format('d M Y, H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
            {{ $attempts->links() }}
        @endif
    </div>
</x-layouts.authenticated>
