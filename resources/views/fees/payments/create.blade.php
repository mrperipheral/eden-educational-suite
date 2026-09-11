@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $money = fn ($n) => number_format((float) $n, 2);
@endphp

<x-layouts.authenticated :title="__('Record payment — :name', ['name' => $student->fullName()])">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('fees.students.show', $student) }}" class="text-brand-600 hover:text-brand-700">{{ __('← :name', ['name' => $student->fullName()]) }}</a>
        </p>

        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        <x-card :title="__('Payment details')">
            <form method="POST" action="{{ route('fees.students.payments.store', $student) }}" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-input name="amount" type="number" step="0.01" min="0.01" :label="__('Amount received')" :value="old('amount')" required />
                    <x-input name="payment_date" type="date" :label="__('Payment date')" :value="old('payment_date', now()->toDateString())" required />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-input name="reference" :label="__('Reference / receipt number')" :value="old('reference')" required />
                    <div class="space-y-1">
                        <label for="method" class="block text-sm font-medium text-gray-700">{{ __('Method') }}</label>
                        <select id="method" name="method" class="{{ $selectClass }}">
                            @foreach ($methods as $method)
                                <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ $method->label() }}</option>
                            @endforeach
                        </select>
                        @error('method') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-input name="payer_name" :label="__('Payer name (optional)')" :value="old('payer_name')" />
                    <x-input name="payer_phone" :label="__('Payer phone (optional)')" :value="old('payer_phone')" />
                    <x-input name="payer_email" type="email" :label="__('Payer email (optional)')" :value="old('payer_email')" />
                </div>

                <div class="space-y-1">
                    <label for="notes" class="block text-sm font-medium text-gray-700">{{ __('Notes (optional)') }}</label>
                    <textarea id="notes" name="notes" rows="2" class="{{ $selectClass }}">{{ old('notes') }}</textarea>
                    @error('notes') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($outstandingCharges->isNotEmpty())
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('Allocate to outstanding charges (optional)') }}</h3>
                        <p class="mt-1 text-xs text-gray-500">{{ __('Leave blank to record this as an unallocated credit you can allocate later.') }}</p>
                        <div class="mt-3 space-y-2">
                            @foreach ($outstandingCharges as $charge)
                                <div class="flex items-center justify-between gap-3 rounded-md border border-gray-200 p-2">
                                    <div class="min-w-0 text-sm">
                                        <p class="truncate font-medium text-gray-900">{{ $charge->description }}</p>
                                        <p class="text-xs text-gray-500">{{ __('Outstanding') }}: {{ $money($charge->outstandingBalance()) }}</p>
                                    </div>
                                    <input type="number" step="0.01" min="0" max="{{ $charge->outstandingBalance() }}"
                                        name="allocations[{{ $charge->id }}]" value="{{ old('allocations.'.$charge->id) }}"
                                        placeholder="0.00"
                                        class="w-28 shrink-0 rounded-md border-0 px-2 py-1 text-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-brand-500">
                                </div>
                            @endforeach
                        </div>
                        @error('allocations') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <x-button type="submit">{{ __('Record payment') }}</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
