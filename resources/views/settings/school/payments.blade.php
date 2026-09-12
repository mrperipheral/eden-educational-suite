<x-layouts.authenticated :title="__('Payment settings')">
    <div class="max-w-2xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @include('settings.school._nav')

        <x-card :title="__('Online payment (Paystack)')">
            <p class="mb-4 text-sm text-gray-600">
                {{ __('Let parents and students pay outstanding fees online through Paystack. Optional — the Fees module works fully with this off; nothing here changes how manual payments are recorded.') }}
            </p>

            @can('school.settings.update')
                <form method="POST" action="{{ route('settings.school.payments.update') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="paystack_enabled" value="1" @checked(old('paystack_enabled', $settings->paystack_enabled))
                            class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        {{ __('Enable online payment for this school') }}
                    </label>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="paystack_test_mode" value="1" @checked(old('paystack_test_mode', $settings->paystack_test_mode))
                            class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        {{ __('Test mode (use your Paystack test keys — pk_test_… / sk_test_…)') }}
                    </label>

                    <x-input name="paystack_public_key" :label="__('Public key')" :value="old('paystack_public_key', $settings->paystack_public_key)"
                        placeholder="pk_test_… or pk_live_…" />

                    <div class="space-y-1">
                        <label for="paystack_secret_key" class="block text-sm font-medium text-gray-700">{{ __('Secret key') }}</label>
                        <input id="paystack_secret_key" name="paystack_secret_key" type="password" autocomplete="off"
                            placeholder="{{ $settings->paystack_secret_key ? __('•••• configured — leave blank to keep it') : 'sk_test_… or sk_live_…' }}"
                            class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                        <p class="text-xs text-gray-500">{{ __('Never shown once saved. Leave blank to keep the current key.') }}</p>
                        @error('paystack_secret_key') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="rounded-md bg-gray-50 p-3 text-xs text-gray-500">
                        {{ __('Webhook URL (add this in your Paystack dashboard):') }}
                        <code class="block break-all font-mono text-gray-700">{{ route('webhooks.paystack') }}</code>
                    </div>

                    <x-button type="submit">{{ __('Save payment settings') }}</x-button>
                </form>
            @else
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                    <dt class="text-gray-500">{{ __('Enabled') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ $settings->paystack_enabled ? __('Yes') : __('No') }}</dd>
                    <dt class="text-gray-500">{{ __('Mode') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ $settings->paystack_test_mode ? __('Test') : __('Live') }}</dd>
                </dl>
                <p class="mt-4 text-xs text-gray-400">{{ __('You have read-only access to school settings.') }}</p>
            @endcan
        </x-card>
    </div>
</x-layouts.authenticated>
