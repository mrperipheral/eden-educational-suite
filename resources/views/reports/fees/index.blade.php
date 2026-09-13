@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $tabs = ['summary' => __('Collection summary'), 'outstanding' => __('Outstanding balances'), 'payments' => __('Payment activity')];
@endphp

<x-layouts.authenticated :title="__('Fee Reports')">
    <x-slot:actions>
        @can('reports.export')
            <x-button :href="route('reports.fees.export', array_merge($filters, ['tab' => $tab]))" size="sm" variant="secondary">{{ __('Export CSV') }}</x-button>
        @endcan
    </x-slot:actions>

    <div class="space-y-6">
        <p class="text-sm"><a href="{{ route('reports.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Reports') }}</a></p>

        <nav class="flex flex-wrap gap-2 border-b border-gray-200 pb-2">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('reports.fees.index', array_merge($filters, ['tab' => $key])) }}"
                    @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-brand-50 text-brand-700' => $tab === $key, 'text-gray-600 hover:bg-gray-100' => $tab !== $key])>
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('reports.fees.index') }}" class="flex flex-wrap items-end gap-2" x-data="{ levelId: '{{ $filters['level'] ?? '' }}' }">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @if ($tab !== 'payments')
                <div class="space-y-1">
                    <label for="session" class="block text-xs font-medium text-gray-700">{{ __('Session') }}</label>
                    <select id="session" name="session" class="{{ $selectClass }}">
                        <option value="">{{ __('All sessions') }}</option>
                        @foreach ($sessions as $session)
                            <option value="{{ $session->id }}" @selected(($filters['session'] ?? null) == $session->id)>{{ $session->name }}</option>
                        @endforeach
                    </select>
                </div>
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
            @else
                <div class="space-y-1">
                    <label for="method" class="block text-xs font-medium text-gray-700">{{ __('Method') }}</label>
                    <select id="method" name="method" class="{{ $selectClass }}">
                        <option value="">{{ __('All methods') }}</option>
                        @foreach (\App\Enums\PaymentMethod::all() as $method)
                            <option value="{{ $method->value }}" @selected(($filters['method'] ?? null) === $method->value)>{{ $method->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label for="from" class="block text-xs font-medium text-gray-700">{{ __('From') }}</label>
                    <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="{{ $selectClass }}">
                </div>
                <div class="space-y-1">
                    <label for="to" class="block text-xs font-medium text-gray-700">{{ __('To') }}</label>
                    <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="{{ $selectClass }}">
                </div>
            @endif
            <x-button type="submit" size="sm" variant="secondary">{{ __('Filter') }}</x-button>
            @if (array_filter($filters))
                <x-button :href="route('reports.fees.index', ['tab' => $tab])" size="sm" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        @if ($tab === 'summary')
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <x-card><p class="text-xs text-gray-500">{{ __('Total charged') }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $summary['total_charged'] }}</p></x-card>
                <x-card><p class="text-xs text-gray-500">{{ __('Total discount') }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $summary['total_discount'] }}</p></x-card>
                <x-card><p class="text-xs text-gray-500">{{ __('Total waived') }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $summary['total_waived'] }}</p></x-card>
                <x-card><p class="text-xs text-gray-500">{{ __('Total collected') }}</p><p class="mt-1 text-xl font-semibold text-green-700">{{ $summary['total_collected'] }}</p></x-card>
                <x-card><p class="text-xs text-gray-500">{{ __('Total outstanding') }}</p><p class="mt-1 text-xl font-semibold text-red-700">{{ $summary['total_outstanding'] }}</p></x-card>
            </div>
        @elseif ($tab === 'outstanding')
            @if ($outstanding->isEmpty())
                <x-empty-state :title="__('No outstanding charges match')" />
            @else
                <x-card :padding="false">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead><tr class="text-left text-xs font-medium text-gray-500">
                            <th class="px-4 py-2">{{ __('Student') }}</th><th class="px-4 py-2">{{ __('Admission #') }}</th>
                            <th class="px-4 py-2">{{ __('Charged') }}</th><th class="px-4 py-2">{{ __('Discount') }}</th>
                            <th class="px-4 py-2">{{ __('Waived') }}</th><th class="px-4 py-2">{{ __('Outstanding') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($outstanding as $row)
                                <tr>
                                    <td class="px-4 py-2">{{ $row->student?->fullName() }}</td>
                                    <td class="px-4 py-2">{{ $row->student?->admission_number }}</td>
                                    <td class="px-4 py-2">{{ $row->total_amount }}</td>
                                    <td class="px-4 py-2">{{ $row->total_discount }}</td>
                                    <td class="px-4 py-2">{{ $row->total_waived }}</td>
                                    <td class="px-4 py-2 font-medium text-red-700">{{ $row->outstanding_balance }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
                {{ $outstanding->links() }}
            @endif
        @else
            @if ($payments->isEmpty())
                <x-empty-state :title="__('No payments match')" />
            @else
                <x-card :padding="false">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead><tr class="text-left text-xs font-medium text-gray-500">
                            <th class="px-4 py-2">{{ __('Date') }}</th><th class="px-4 py-2">{{ __('Student') }}</th>
                            <th class="px-4 py-2">{{ __('Amount') }}</th><th class="px-4 py-2">{{ __('Method') }}</th><th class="px-4 py-2">{{ __('Reference') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($payments as $payment)
                                <tr>
                                    <td class="px-4 py-2">{{ $payment->payment_date?->format('d M Y') }}</td>
                                    <td class="px-4 py-2">{{ $payment->student?->fullName() }}</td>
                                    <td class="px-4 py-2">{{ $payment->amount }}</td>
                                    <td class="px-4 py-2"><x-badge variant="gray">{{ $payment->method?->label() }}</x-badge></td>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ $payment->reference }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
                {{ $payments->links() }}
            @endif
        @endif
    </div>
</x-layouts.authenticated>
