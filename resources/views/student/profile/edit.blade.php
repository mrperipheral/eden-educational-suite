<x-layouts.authenticated :title="__('My Profile')">
    <div class="max-w-2xl space-y-6">
        @include('student._nav', ['active' => 'profile'])

        @if (! $student)
            <x-empty-state
                :title="__('Your account is not linked to a student record yet')"
                :description="__('Please contact your school administrator.')"
            />
        @else
            <x-card :title="__('Student information')">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                    <dt class="text-gray-500">{{ __('Name') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ $student->fullName() }}</dd>

                    <dt class="text-gray-500">{{ __('Admission number') }}</dt>
                    <dd class="font-mono text-gray-900 sm:col-span-2">{{ $student->admission_number }}</dd>

                    <dt class="text-gray-500">{{ __('Status') }}</dt>
                    <dd class="sm:col-span-2"><x-badge :variant="$student->status->badgeVariant()">{{ $student->status->label() }}</x-badge></dd>

                    @if ($student->currentEnrollment)
                        <dt class="text-gray-500">{{ __('Class') }}</dt>
                        <dd class="text-gray-900 sm:col-span-2">
                            {{ $student->currentEnrollment->level?->name }}
                            @if ($student->currentEnrollment->arm) — {{ $student->currentEnrollment->arm->name }} @endif
                        </dd>

                        <dt class="text-gray-500">{{ __('Session') }}</dt>
                        <dd class="text-gray-900 sm:col-span-2">{{ $student->currentEnrollment->session?->name }}</dd>

                        @if ($student->currentEnrollment->period)
                            <dt class="text-gray-500">{{ __('Term') }}</dt>
                            <dd class="text-gray-900 sm:col-span-2">{{ $student->currentEnrollment->period->name }}</dd>
                        @endif
                    @else
                        <dt class="text-gray-500">{{ __('Class') }}</dt>
                        <dd class="italic text-gray-500 sm:col-span-2">{{ __('Not currently enrolled in a class.') }}</dd>
                    @endif
                </dl>
                <p class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-500">
                    {{ __('These details are managed by your school. Contact the school office to update them.') }}
                </p>
            </x-card>

            <x-card :title="__('Account')">
                <p class="text-sm text-gray-700">{{ __('Your sign-in email, password and account settings are managed separately.') }}</p>
                <div class="mt-3">
                    <x-button :href="route('settings.profile.edit')" size="sm" variant="secondary">{{ __('Account settings') }}</x-button>
                </div>
            </x-card>
        @endif
    </div>
</x-layouts.authenticated>
