<x-layouts.authenticated :title="__('School branding')">
    <div class="max-w-2xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @include('settings.school._nav')

        <x-card :title="__('Logo')">
            <div class="space-y-4">
                @if ($settings->hasLogo())
                    <div class="flex items-center gap-4">
                        <img
                            src="{{ route('settings.school.branding.logo.show') }}"
                            alt="{{ __('Current school logo') }}"
                            class="h-20 w-20 rounded-md object-contain ring-1 ring-gray-200"
                        >
                        @can('school.settings.update')
                            <x-confirm
                                :action="route('settings.school.branding.logo.destroy')"
                                :title="__('Remove logo?')"
                                :message="__('The current logo will be permanently deleted.')"
                                :confirm="__('Remove logo')"
                            >
                                {{ __('Remove logo') }}
                            </x-confirm>
                        @endcan
                    </div>
                @else
                    <x-empty-state
                        :title="__('No logo uploaded')"
                        :description="__('Upload a square JPEG, PNG or WebP image (48–1600px, max 2MB).')"
                    />
                @endif

                @can('school.settings.update')
                    <form method="POST" action="{{ route('settings.school.branding.update') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div class="space-y-1">
                            <label for="logo" class="block text-sm font-medium text-gray-700">{{ __('Upload a new logo') }}</label>
                            <input
                                id="logo"
                                name="logo"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100"
                            >
                            @error('logo') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1">
                            <label for="brand_color" class="block text-sm font-medium text-gray-700">{{ __('Brand colour') }}</label>
                            <div class="flex items-center gap-3">
                                <input
                                    id="brand_color"
                                    name="brand_color"
                                    type="text"
                                    value="{{ old('brand_color', $settings->brand_color) }}"
                                    placeholder="#1D4ED8"
                                    maxlength="7"
                                    class="block w-40 rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500"
                                >
                                @if ($settings->brand_color)
                                    <span class="inline-block h-8 w-8 rounded-md ring-1 ring-gray-200" style="background-color: {{ $settings->brand_color }}"></span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500">{{ __('A 6-digit hex value used in the app shell and portals.') }}</p>
                            @error('brand_color') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="pt-1">
                            <x-button type="submit">{{ __('Save branding') }}</x-button>
                        </div>
                    </form>
                @else
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                        <dt class="text-gray-500">{{ __('Brand colour') }}</dt>
                        <dd class="text-gray-900 sm:col-span-2">
                            @if ($settings->brand_color)
                                <span class="inline-flex items-center gap-2">
                                    <span class="inline-block h-4 w-4 rounded ring-1 ring-gray-200" style="background-color: {{ $settings->brand_color }}"></span>
                                    {{ $settings->brand_color }}
                                </span>
                            @else
                                —
                            @endif
                        </dd>
                    </dl>
                    <p class="mt-4 text-xs text-gray-400">{{ __('You have read-only access to school settings.') }}</p>
                @endcan
            </div>
        </x-card>
    </div>
</x-layouts.authenticated>
