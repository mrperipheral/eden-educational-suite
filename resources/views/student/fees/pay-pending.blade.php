<x-layouts.authenticated :title="__('Confirming payment…')">
    <div class="max-w-md">
        @include('student._nav', ['active' => 'fees'])

        <x-card :title="__('We could not confirm this payment yet')">
            <p class="text-sm text-gray-600">
                {{ __('We were unable to reach Paystack to confirm your payment just now. If money left your account, it will still be picked up automatically shortly — please check back in a few minutes.') }}
            </p>
            <div class="mt-4">
                <x-button :href="route('student.fees.show')">{{ __('Back to fee statement') }}</x-button>
            </div>
        </x-card>
    </div>
</x-layouts.authenticated>
