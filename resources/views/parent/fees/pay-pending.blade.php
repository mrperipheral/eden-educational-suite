<x-layouts.authenticated :title="__('Confirming payment…')">
    <div class="max-w-md">
        @include('parent._child-nav', ['student' => $student, 'siblings' => collect([$student]), 'active' => 'fees', 'sectionRoute' => 'parent.fees.show'])

        <x-card :title="__('We could not confirm this payment yet')">
            <p class="text-sm text-gray-600">
                {{ __('We were unable to reach Paystack to confirm your payment just now. If money left your account, it will still be picked up automatically shortly — please check back in a few minutes.') }}
            </p>
            <div class="mt-4">
                <x-button :href="route('parent.fees.show', $student)">{{ __('Back to fee statement') }}</x-button>
            </div>
        </x-card>
    </div>
</x-layouts.authenticated>
