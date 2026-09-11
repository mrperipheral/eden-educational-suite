<x-layouts.authenticated :title="__('Profile')">
    <div class="max-w-2xl space-y-6">
        <x-card :title="__('Guardian details')">
            @if ($guardian)
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                    <dt class="text-gray-500">{{ __('Name') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ $guardian->fullName() }}</dd>

                    <dt class="text-gray-500">{{ __('Phone') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ $guardian->phone ?: '—' }}</dd>

                    <dt class="text-gray-500">{{ __('Alternate phone') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ $guardian->alt_phone ?: '—' }}</dd>

                    <dt class="text-gray-500">{{ __('Email') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ $guardian->email ?: '—' }}</dd>

                    <dt class="text-gray-500">{{ __('Address') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">
                        {{ collect([$guardian->address_line1, $guardian->address_line2, $guardian->city, $guardian->state])->filter()->join(', ') ?: '—' }}
                    </dd>
                </dl>
                <p class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-500">
                    {{ __('These details are managed by your school. Contact the school office to update them.') }}
                </p>
            @else
                <p class="text-sm text-gray-500">{{ __('No guardian record is linked to your account yet. Please contact your school administrator.') }}</p>
            @endif
        </x-card>

        <x-card :title="__('Account')">
            <p class="text-sm text-gray-700">{{ __('Your sign-in email, password and account settings are managed separately.') }}</p>
            <div class="mt-3">
                <x-button :href="route('settings.profile.edit')" size="sm" variant="secondary">{{ __('Account settings') }}</x-button>
            </div>
        </x-card>
    </div>
</x-layouts.authenticated>
