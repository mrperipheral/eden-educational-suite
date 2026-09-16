@php
    $money = fn ($n) => number_format((float) $n, 2);
@endphp

<x-layouts.authenticated :title="__('Fees')">
    @can('fees.manage')
        <x-slot:actions>
            <x-button :href="route('fees.categories.index')" size="sm" variant="ghost">{{ __('Categories') }}</x-button>
            <x-button :href="route('fees.structures.index')" size="sm">{{ __('Fee structures') }}</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-6">
        <x-greeting :context="__('Here\'s the fees picture for today.')" />

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-card>
                <p class="text-xs font-medium text-gray-500">{{ __('Total charged') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $money($totalCharged) }}</p>
            </x-card>
            <x-card>
                <p class="text-xs font-medium text-gray-500">{{ __('Total discounted') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $money($totalDiscounted) }}</p>
            </x-card>
            <x-card>
                <p class="text-xs font-medium text-gray-500">{{ __('Total collected') }}</p>
                <p class="mt-1 text-2xl font-bold text-green-700">{{ $money($totalCollected) }}</p>
            </x-card>
        </div>

        @isset($recentPayments)
            <x-card :title="__('Recent payments')">
                @if ($recentPayments->isEmpty())
                    <x-empty-state :title="__('No payments recorded yet')" />
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($recentPayments as $payment)
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-gray-900">{{ $payment->student?->fullName() }}</p>
                                    <p class="text-xs text-gray-500">{{ $payment->created_at?->diffForHumans() }}</p>
                                </div>
                                <span class="shrink-0 font-bold text-green-700">{{ $money($payment->amount) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        @endisset

        <form method="GET" action="{{ route('fees.index') }}" class="flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-500">{{ __('Search students') }}</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('Name or admission number') }}"
                    class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
            </div>
            <x-button type="submit" variant="secondary">{{ __('Search') }}</x-button>
            @if ($search)
                <x-button :href="route('fees.index')" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        @if ($rows->isEmpty())
            <x-empty-state
                :title="$search ? __('No students match') : __('No fee activity yet')"
                :description="$search ? __('Try a different search.') : __('Raise a charge for a student from their fee statement to get started.')"
            />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($rows as $row)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route('fees.students.show', $row['student']) }}" class="hover:text-brand-700">
                                        {{ $row['student']->fullName() }}
                                    </a>
                                </p>
                                <p class="text-xs text-gray-500">{{ $row['student']->admission_number }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-xs font-medium text-gray-500">{{ __('Outstanding') }}</p>
                                <p @class([
                                    'text-sm font-semibold',
                                    'text-red-700' => bccomp($row['outstanding'], '0.00', 2) === 1,
                                    'text-green-700' => bccomp($row['outstanding'], '0.00', 2) === 0,
                                ])>{{ $money($row['outstanding']) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $students->links() }}
        @endif
    </div>
</x-layouts.authenticated>
