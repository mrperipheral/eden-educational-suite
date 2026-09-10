@php
    $textareaClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<div class="space-y-6">
    <div>
        <h3 class="text-sm font-semibold text-gray-900">{{ __('Name') }}</h3>
        <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-input name="first_name" :label="__('First name')" :value="old('first_name', $guardian->first_name)" required />
            <x-input name="middle_name" :label="__('Middle name')" :value="old('middle_name', $guardian->middle_name)" />
            <x-input name="last_name" :label="__('Last name')" :value="old('last_name', $guardian->last_name)" required />
        </div>
        <div class="mt-4 sm:w-1/3">
            <x-input name="preferred_name" :label="__('Preferred name')" :value="old('preferred_name', $guardian->preferred_name)"
                :hint="__('Shown in lists if set.')" />
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-gray-900">{{ __('Contact') }}</h3>
        <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-input name="phone" :label="__('Phone')" :value="old('phone', $guardian->phone)" />
            <x-input name="alt_phone" :label="__('Alternate phone')" :value="old('alt_phone', $guardian->alt_phone)" />
        </div>
        <div class="mt-4">
            <x-input name="email" type="email" :label="__('Email')" :value="old('email', $guardian->email)" />
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-gray-900">{{ __('Address') }}</h3>
        <div class="mt-2 space-y-4">
            <x-input name="address_line1" :label="__('Address line 1')" :value="old('address_line1', $guardian->address_line1)" />
            <x-input name="address_line2" :label="__('Address line 2')" :value="old('address_line2', $guardian->address_line2)" />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="city" :label="__('City')" :value="old('city', $guardian->city)" />
                <x-input name="state" :label="__('State / Region')" :value="old('state', $guardian->state)" />
            </div>
        </div>
    </div>

    <div class="space-y-1">
        <label for="notes" class="block text-sm font-medium text-gray-700">{{ __('Notes') }}</label>
        <textarea id="notes" name="notes" rows="3" class="{{ $textareaClass }}">{{ old('notes', $guardian->notes) }}</textarea>
        @error('notes') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
