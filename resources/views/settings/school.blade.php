<x-layouts.authenticated :title="__('School settings')">
    <div class="max-w-2xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <nav class="flex gap-4 border-b border-gray-200 pb-2 text-sm">
            <span class="font-medium text-brand-700">{{ __('General') }}</span>
            <a href="{{ route('academic-sessions.index') }}" class="text-gray-500 hover:text-gray-800">{{ __('Academic sessions') }}</a>
        </nav>

        <x-card :title="__('General')">
            @can('school.settings.update')
                <form method="POST" action="{{ route('settings.school.update') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="space-y-1">
                        <label for="timezone" class="block text-sm font-medium text-gray-700">{{ __('Timezone') }}</label>
                        <select
                            id="timezone"
                            name="timezone"
                            class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500"
                        >
                            @foreach (timezone_identifiers_list() as $tz)
                                <option value="{{ $tz }}" @selected(old('timezone', $settings->timezone) === $tz)>{{ $tz }}</option>
                            @endforeach
                        </select>
                        @error('timezone') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <x-input name="locale" :label="__('Locale')" :value="old('locale', $settings->locale)" required :hint="__('e.g. en, en-GB')" />
                    <x-input name="contact_email" type="email" :label="__('Contact email')" :value="old('contact_email', $settings->contact_email)" />
                    <x-input name="contact_phone" :label="__('Contact phone')" :value="old('contact_phone', $settings->contact_phone)" />

                    <div class="pt-2">
                        <x-button type="submit">{{ __('Save settings') }}</x-button>
                    </div>
                </form>
            @else
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                    <dt class="text-gray-500">{{ __('Timezone') }}</dt>
                    <dd class="sm:col-span-2 text-gray-900">{{ $settings->timezone }}</dd>
                    <dt class="text-gray-500">{{ __('Locale') }}</dt>
                    <dd class="sm:col-span-2 text-gray-900">{{ $settings->locale }}</dd>
                    <dt class="text-gray-500">{{ __('Contact email') }}</dt>
                    <dd class="sm:col-span-2 text-gray-900">{{ $settings->contact_email ?: '—' }}</dd>
                    <dt class="text-gray-500">{{ __('Contact phone') }}</dt>
                    <dd class="sm:col-span-2 text-gray-900">{{ $settings->contact_phone ?: '—' }}</dd>
                </dl>
                <p class="mt-4 text-xs text-gray-400">{{ __('You have read-only access to school settings.') }}</p>
            @endcan
        </x-card>
    </div>
</x-layouts.authenticated>
