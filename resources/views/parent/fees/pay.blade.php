@php
    $money = fn ($n) => number_format((float) $n, 2);
@endphp

<x-layouts.authenticated :title="__('Pay fees — :name', ['name' => $student->fullName()])">
    <div class="space-y-6">
        @include('parent._child-nav', [
            'student' => $student, 'siblings' => $siblings, 'active' => 'fees',
            'sectionRoute' => 'parent.fees.show',
        ])

        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        <div class="max-w-md">
            @if (! $paystackReady)
                <x-empty-state
                    :title="__('Online payment is not available')"
                    :description="__('This school has not enabled online payment yet. Please contact the school for other ways to pay.')"
                />
            @elseif (bccomp($outstanding, '0.00', 2) <= 0)
                <x-empty-state
                    :title="__('Nothing outstanding')"
                    :description="__('There is no outstanding balance to pay right now.')"
                />
            @else
                <x-card :title="__('Pay online')">
                    <p class="text-sm text-gray-600">
                        {{ __('Outstanding balance') }}:
                        <span class="font-semibold text-gray-900">{{ $money($outstanding) }}</span>
                    </p>

                    <form method="POST" action="{{ route('parent.fees.pay.store', $student) }}" class="mt-4 space-y-4">
                        @csrf
                        <x-input name="amount" type="number" step="0.01" min="0.01" :max="$outstanding"
                            :label="__('Amount to pay')" :value="old('amount', $outstanding)" required />
                        <p class="text-xs text-gray-500">{{ __('Paid amounts are applied to the oldest outstanding charges first.') }}</p>
                        <x-button type="submit">{{ __('Continue to Paystack') }}</x-button>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.authenticated>
