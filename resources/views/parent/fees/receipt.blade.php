@php
    $money = fn ($n) => number_format((float) $n, 2);
    $payment = $transaction->payment;
@endphp

<x-layouts.authenticated :title="__('Payment receipt')">
    <div class="max-w-md space-y-6">
        <x-card>
            <div class="text-center">
                <x-badge :variant="$transaction->status->badgeVariant()" class="mb-2">{{ $transaction->status->label() }}</x-badge>
                <h2 class="text-lg font-semibold text-gray-900">{{ $school->name }}</h2>
                <p class="text-sm text-gray-500">{{ __('Payment for :name', ['name' => $student->fullName()]) }}</p>
            </div>

            <dl class="mt-6 space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Amount') }}</dt><dd class="font-semibold text-gray-900">{{ $money($transaction->amount) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Payer') }}</dt><dd class="text-gray-900">{{ $transaction->initiatedBy?->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Date') }}</dt><dd class="text-gray-900">{{ ($transaction->paid_at ?? $transaction->verified_at)?->format('d M Y, H:i') }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Reference') }}</dt><dd class="font-mono text-xs text-gray-900">{{ $transaction->reference }}</dd></div>
                @if ($transaction->provider_transaction_id)
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Paystack reference') }}</dt><dd class="font-mono text-xs text-gray-900">{{ $transaction->provider_transaction_id }}</dd></div>
                @endif
                @if ($transaction->channel)
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Channel') }}</dt><dd class="text-gray-900">{{ ucfirst($transaction->channel) }}</dd></div>
                @endif
            </dl>

            @if ($transaction->status->value === 'successful' && $payment?->allocations->isNotEmpty())
                <div class="mt-6 border-t border-gray-100 pt-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Applied to') }}</h3>
                    <ul class="mt-2 space-y-1 text-sm text-gray-700">
                        @foreach ($payment->allocations as $allocation)
                            <li class="flex justify-between">
                                <span>{{ $allocation->charge?->description ?? __('Charge') }}</span>
                                <span>{{ $money($allocation->amount) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($transaction->status->value !== 'successful' && $transaction->failure_reason)
                <p class="mt-4 text-sm text-red-600">{{ $transaction->failure_reason }}</p>
            @endif

            <div class="mt-6">
                <x-button :href="route('parent.fees.show', $student)" class="w-full justify-center">{{ __('Back to fee statement') }}</x-button>
            </div>
        </x-card>
    </div>
</x-layouts.authenticated>
