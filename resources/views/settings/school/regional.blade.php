<x-layouts.authenticated :title="__('Regional settings')">
    <div class="max-w-2xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @include('settings.school._nav')

        <x-card :title="__('Regional & formatting')">
            @php
                $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
                $months = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\CarbonImmutable::create(null, $m, 1)->translatedFormat('F')]);
            @endphp

            @can('school.settings.update')
                <form method="POST" action="{{ route('settings.school.regional.update') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="space-y-1">
                        <label for="timezone" class="block text-sm font-medium text-gray-700">{{ __('Timezone') }}</label>
                        <select id="timezone" name="timezone" class="{{ $selectClass }}">
                            @foreach (timezone_identifiers_list() as $tz)
                                <option value="{{ $tz }}" @selected(old('timezone', $settings->timezone) === $tz)>{{ $tz }}</option>
                            @endforeach
                        </select>
                        @error('timezone') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label for="locale" class="block text-sm font-medium text-gray-700">{{ __('Language') }}</label>
                            <select id="locale" name="locale" class="{{ $selectClass }}">
                                @foreach (config('school-settings.locales') as $code => $name)
                                    <option value="{{ $code }}" @selected(old('locale', $settings->locale) === $code)>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('locale') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1">
                            <label for="currency" class="block text-sm font-medium text-gray-700">{{ __('Currency') }}</label>
                            <select id="currency" name="currency" class="{{ $selectClass }}">
                                @foreach (config('school-settings.currencies') as $code => $name)
                                    <option value="{{ $code }}" @selected(old('currency', $settings->currency) === $code)>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('currency') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label for="date_format" class="block text-sm font-medium text-gray-700">{{ __('Date format') }}</label>
                            <select id="date_format" name="date_format" class="{{ $selectClass }}">
                                @foreach (\App\Enums\DateFormat::cases() as $format)
                                    <option value="{{ $format->value }}" @selected(old('date_format', $settings->date_format?->value) === $format->value)>
                                        {{ $format->label() }} — {{ $format->example() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('date_format') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1">
                            <label for="week_starts_on" class="block text-sm font-medium text-gray-700">{{ __('Week starts on') }}</label>
                            <select id="week_starts_on" name="week_starts_on" class="{{ $selectClass }}">
                                @foreach (\App\Enums\Weekday::cases() as $day)
                                    <option value="{{ $day->value }}" @selected((int) old('week_starts_on', $settings->week_starts_on?->value) === $day->value)>
                                        {{ $day->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('week_starts_on') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label for="academic_year_start_month" class="block text-sm font-medium text-gray-700">{{ __('Academic year starts in') }}</label>
                        <select id="academic_year_start_month" name="academic_year_start_month" class="{{ $selectClass }}">
                            @foreach ($months as $number => $name)
                                <option value="{{ $number }}" @selected((int) old('academic_year_start_month', $settings->academic_year_start_month) === $number)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500">{{ __('Used when Academic Management proposes new sessions. It does not change existing sessions.') }}</p>
                        @error('academic_year_start_month') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="pt-2">
                        <x-button type="submit">{{ __('Save regional settings') }}</x-button>
                    </div>
                </form>
            @else
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                    <dt class="text-gray-500">{{ __('Timezone') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ $settings->timezone }}</dd>
                    <dt class="text-gray-500">{{ __('Language') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ config("school-settings.locales.{$settings->locale}", $settings->locale) }}</dd>
                    <dt class="text-gray-500">{{ __('Currency') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ config("school-settings.currencies.{$settings->currency}", $settings->currency) }}</dd>
                    <dt class="text-gray-500">{{ __('Date format') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ $settings->date_format?->label() }} — {{ $settings->date_format?->example() }}</dd>
                    <dt class="text-gray-500">{{ __('Week starts on') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ $settings->week_starts_on?->label() }}</dd>
                    <dt class="text-gray-500">{{ __('Academic year starts in') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ $months[$settings->academic_year_start_month] ?? $settings->academic_year_start_month }}</dd>
                </dl>
                <p class="mt-4 text-xs text-gray-400">{{ __('You have read-only access to school settings.') }}</p>
            @endcan
        </x-card>
    </div>
</x-layouts.authenticated>
