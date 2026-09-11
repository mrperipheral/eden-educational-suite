<x-layouts.authenticated :title="__('Fee statement — :name', ['name' => $student->fullName()])">
    <x-slot:actions>
        @can('fees.manage')
            <x-button :href="route('fees.students.charges.create', $student)" size="sm" variant="ghost">{{ __('Add charge') }}</x-button>
        @endcan
        @can('fees.record-payment')
            <x-button :href="route('fees.students.payments.create', $student)" size="sm">{{ __('Record payment') }}</x-button>
        @endcan
    </x-slot:actions>

    <div class="space-y-6">
        <p class="text-sm">
            <a href="{{ route('fees.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Fees') }}</a>
        </p>

        <x-card>
            <h2 class="text-lg font-semibold text-gray-900">{{ $student->fullName() }}</h2>
            <p class="text-xs text-gray-500">{{ $student->admission_number }}</p>
        </x-card>

        @include('fees._statement-body', ['readOnly' => false])
    </div>
</x-layouts.authenticated>
