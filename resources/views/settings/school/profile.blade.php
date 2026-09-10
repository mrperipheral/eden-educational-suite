<x-layouts.authenticated :title="__('School settings')">
    <div class="max-w-2xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @include('settings.school._nav')

        {{-- System-controlled identity — set by platform administration --}}
        <x-card :title="__('School identity')">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                <dt class="text-gray-500">{{ __('Name') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">{{ $school->name }}</dd>

                <dt class="text-gray-500">{{ __('Slug') }}</dt>
                <dd class="text-gray-900 sm:col-span-2"><code class="text-xs">{{ $school->slug }}</code></dd>

                <dt class="text-gray-500">{{ __('Status') }}</dt>
                <dd class="sm:col-span-2">
                    <x-badge :variant="$school->isActive() ? 'success' : 'warning'">{{ $school->status->label() }}</x-badge>
                </dd>
            </dl>
            <p class="mt-3 flex items-center gap-1.5 text-xs text-gray-400">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                {{ __('Managed by platform administration.') }}
            </p>
        </x-card>

        <x-card :title="__('Contact & address')">
            @can('school.settings.update')
                <form method="POST" action="{{ route('settings.school.update') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input name="contact_email" type="email" :label="__('Contact email')" :value="old('contact_email', $settings->contact_email)" autocomplete="email" />
                        <x-input name="contact_phone" :label="__('Contact phone')" :value="old('contact_phone', $settings->contact_phone)" autocomplete="tel" />
                    </div>

                    <x-input name="website_url" type="url" :label="__('Website')" :value="old('website_url', $settings->website_url)" placeholder="https://" />

                    <x-input name="address_line1" :label="__('Address line 1')" :value="old('address_line1', $settings->address_line1)" />
                    <x-input name="address_line2" :label="__('Address line 2')" :value="old('address_line2', $settings->address_line2)" />

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input name="city" :label="__('City')" :value="old('city', $settings->city)" />
                        <x-input name="state" :label="__('State / Region')" :value="old('state', $settings->state)" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input name="postal_code" :label="__('Postal code')" :value="old('postal_code', $settings->postal_code)" />
                        <div class="space-y-1">
                            <label for="country" class="block text-sm font-medium text-gray-700">{{ __('Country') }}</label>
                            <select id="country" name="country" class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                <option value="">{{ __('— Not set —') }}</option>
                                @foreach (config('school-settings.countries') as $code => $name)
                                    <option value="{{ $code }}" @selected(old('country', $settings->country) === $code)>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('country') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="pt-2">
                        <x-button type="submit">{{ __('Save profile') }}</x-button>
                    </div>
                </form>
            @else
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                    <dt class="text-gray-500">{{ __('Contact email') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ $settings->contact_email ?: '—' }}</dd>
                    <dt class="text-gray-500">{{ __('Contact phone') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ $settings->contact_phone ?: '—' }}</dd>
                    <dt class="text-gray-500">{{ __('Website') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ $settings->website_url ?: '—' }}</dd>
                    <dt class="text-gray-500">{{ __('Address') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">
                        {{ collect([$settings->address_line1, $settings->address_line2, $settings->city, $settings->state, $settings->postal_code, config("school-settings.countries.{$settings->country}")])->filter()->join(', ') ?: '—' }}
                    </dd>
                </dl>
                <p class="mt-4 text-xs text-gray-400">{{ __('You have read-only access to school settings.') }}</p>
            @endcan
        </x-card>
    </div>
</x-layouts.authenticated>
