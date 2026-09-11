@php
    $money = fn ($n) => number_format((float) $n, 2);
    $readOnly = $readOnly ?? false;
    $canAdjust = ! $readOnly && ($canAdjust ?? false);
@endphp

@if (session('status'))
    <x-alert variant="success">{{ session('status') }}</x-alert>
@endif
@if (session('error'))
    <x-alert variant="danger">{{ session('error') }}</x-alert>
@endif

<div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
    <x-card>
        <p class="text-xs font-medium text-gray-500">{{ __('Charged') }}</p>
        <p class="mt-1 text-lg font-semibold text-gray-900">{{ $money($totalCharged) }}</p>
    </x-card>
    <x-card>
        <p class="text-xs font-medium text-gray-500">{{ __('Discounted') }}</p>
        <p class="mt-1 text-lg font-semibold text-gray-900">{{ $money($totalDiscount) }}</p>
    </x-card>
    <x-card>
        <p class="text-xs font-medium text-gray-500">{{ __('Waived') }}</p>
        <p class="mt-1 text-lg font-semibold text-gray-900">{{ $money($totalWaived) }}</p>
    </x-card>
    <x-card>
        <p class="text-xs font-medium text-gray-500">{{ __('Paid') }}</p>
        <p class="mt-1 text-lg font-semibold text-green-700">{{ $money($totalPaid) }}</p>
    </x-card>
    <x-card>
        <p class="text-xs font-medium text-gray-500">{{ __('Outstanding') }}</p>
        <p @class(['mt-1 text-lg font-semibold', 'text-red-700' => bccomp($totalOutstanding, '0.00', 2) === 1, 'text-green-700' => bccomp($totalOutstanding, '0.00', 2) === 0])>{{ $money($totalOutstanding) }}</p>
    </x-card>
</div>

<x-card :title="__('Charges')" :padding="false">
    @if ($charges->isEmpty())
        <div class="p-6">
            <x-empty-state :title="__('No charges yet')" />
        </div>
    @else
        <ul class="divide-y divide-gray-100">
            @foreach ($charges as $charge)
                <li class="px-4 py-3 sm:px-6">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">
                                {{ $charge->description }}
                                @if ($charge->isWaived()) <x-badge variant="brand">{{ __('Waived') }}</x-badge>
                                @elseif ($charge->isFullyPaid()) <x-badge variant="success">{{ __('Paid') }}</x-badge>
                                @else <x-badge variant="warning">{{ __('Outstanding') }}</x-badge>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $charge->category?->name }} · {{ $charge->session?->name }}{{ $charge->period ? ' · '.$charge->period->name : '' }}
                                · {{ $charge->created_at->toFormattedDateString() }}
                            </p>
                            @if ($charge->isWaived() && $charge->waiver_reason)
                                <p class="text-xs italic text-gray-400">{{ __('Waived') }}: {{ $charge->waiver_reason }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 text-right text-sm">
                            <p class="text-gray-900">{{ $money($charge->amount) }}</p>
                            @if (bccomp($charge->discount_amount, '0.00', 2) === 1)
                                <p class="text-xs text-gray-500">{{ __('-:amount discount', ['amount' => $money($charge->discount_amount)]) }}</p>
                            @endif
                            <p class="text-xs font-medium {{ bccomp($charge->outstandingBalance(), '0.00', 2) === 1 ? 'text-red-700' : 'text-green-700' }}">
                                {{ __('Bal') }}: {{ $money($charge->outstandingBalance()) }}
                            </p>
                        </div>
                    </div>

                    @if ($canAdjust && ! $charge->isFullyPaid())
                        <div class="mt-2 flex flex-wrap gap-2" x-data="{ discounting: false }">
                            <x-button type="button" size="sm" variant="ghost" x-on:click="discounting = ! discounting">{{ __('Discount') }}</x-button>
                            @unless ($charge->isWaived())
                                <x-confirm :action="route('fees.charges.waive', $charge)" method="POST" size="sm" variant="secondary"
                                    :confirm="__('Waive')" :title="__('Waive this charge?')"
                                    :message="__('This forgives the remaining balance outright. It can be reversed.')">
                                    {{ __('Waive') }}
                                </x-confirm>
                            @endunless

                            <form method="POST" action="{{ route('fees.charges.discount', $charge) }}" x-show="discounting" x-cloak class="flex items-end gap-2">
                                @csrf
                                <div>
                                    <label class="block text-xs text-gray-500">{{ __('Amount') }}</label>
                                    <input type="number" step="0.01" min="0.01" name="amount" required
                                        class="w-28 rounded-md border-0 px-2 py-1 text-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-brand-500">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">{{ __('Reason (optional)') }}</label>
                                    <input type="text" name="reason" class="w-40 rounded-md border-0 px-2 py-1 text-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-brand-500">
                                </div>
                                <x-button type="submit" size="sm">{{ __('Apply') }}</x-button>
                            </form>
                        </div>
                    @elseif ($canAdjust && $charge->isWaived())
                        <div class="mt-2">
                            <x-confirm :action="route('fees.charges.unwaive', $charge)" method="POST" size="sm" variant="ghost"
                                :confirm="__('Reverse')" :title="__('Reverse this waiver?')"
                                :message="__('The remaining balance becomes payable again.')">
                                {{ __('Reverse waiver') }}
                            </x-confirm>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>

<x-card :title="__('Payments')" :padding="false">
    @if ($payments->isEmpty())
        <div class="p-6">
            <x-empty-state :title="__('No payments recorded yet')" />
        </div>
    @else
        <ul class="divide-y divide-gray-100">
            @foreach ($payments as $payment)
                <li class="px-4 py-3 sm:px-6">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">
                                {{ $payment->reference }}
                                <x-badge variant="gray">{{ $payment->method->label() }}</x-badge>
                                @if ($payment->isVoided()) <x-badge variant="danger">{{ __('Voided') }}</x-badge> @endif
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $payment->payment_date->toFormattedDateString() }}
                                @unless ($readOnly) · {{ __('Recorded by') }} {{ $payment->recordedBy?->name }} @endunless
                                @if ($payment->payer_name) · {{ __('Payer') }}: {{ $payment->payer_name }} @endif
                            </p>
                            @if ($payment->allocations->isNotEmpty())
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ __('Allocated to') }}:
                                    {{ $payment->allocations->map(fn ($a) => ($a->charge?->description ?? __('deleted charge')).' ('.$money($a->amount).')')->implode(', ') }}
                                </p>
                            @endif
                            @if ($payment->isVoided() && $payment->void_reason)
                                <p class="text-xs italic text-gray-400">{{ __('Void reason') }}: {{ $payment->void_reason }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 text-right text-sm">
                            <p class="font-medium text-gray-900">{{ $money($payment->amount) }}</p>
                            @if (! $payment->isVoided() && bccomp($payment->unallocatedAmount(), '0.00', 2) === 1)
                                <p class="text-xs text-gray-500">{{ __(':amount unallocated', ['amount' => $money($payment->unallocatedAmount())]) }}</p>
                            @endif
                        </div>
                    </div>

                    @if ($canAdjust && ! $payment->isVoided())
                        <div class="mt-2">
                            <x-confirm :action="route('fees.payments.void', $payment)" method="POST" size="sm" variant="danger"
                                :confirm="__('Void payment')" :title="__('Void this payment?')"
                                :message="__('This reverses the payment — it is excluded from balances, but the record is kept. This cannot be undone.')">
                                {{ __('Void') }}
                            </x-confirm>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
