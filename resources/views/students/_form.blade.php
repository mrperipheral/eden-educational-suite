@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<div class="space-y-6">
    <div>
        <h3 class="text-sm font-semibold text-gray-900">{{ __('Name') }}</h3>
        <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-input name="first_name" :label="__('First name')" :value="old('first_name', $student->first_name)" required />
            <x-input name="middle_name" :label="__('Middle name')" :value="old('middle_name', $student->middle_name)" />
            <x-input name="last_name" :label="__('Last name')" :value="old('last_name', $student->last_name)" required />
        </div>
        <div class="mt-4 sm:w-1/3">
            <x-input name="preferred_name" :label="__('Preferred name')" :value="old('preferred_name', $student->preferred_name)"
                :hint="__('Shown in lists if set.')" />
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-gray-900">{{ __('Personal') }}</h3>
        <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-input name="date_of_birth" type="date" :label="__('Date of birth')"
                :value="old('date_of_birth', $student->date_of_birth?->toDateString())" />
            <div class="space-y-1">
                <label for="gender" class="block text-sm font-medium text-gray-700">{{ __('Gender') }}</label>
                <select id="gender" name="gender" class="{{ $selectClass }}">
                    <option value="">{{ __('— Not recorded —') }}</option>
                    @foreach (\App\Enums\Gender::all() as $g)
                        <option value="{{ $g->value }}" @selected(old('gender', $student->gender?->value) === $g->value)>{{ $g->label() }}</option>
                    @endforeach
                </select>
                @error('gender') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-gray-900">{{ __('Admission') }}</h3>
        <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-input name="admission_number" :label="__('Admission / student number')"
                :value="old('admission_number', $student->admission_number)" required placeholder="STU-0001" />
            <x-input name="admitted_on" type="date" :label="__('Admission date')"
                :value="old('admitted_on', $student->admitted_on?->toDateString())" />
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-gray-900">{{ __('Contact') }}</h3>
        <p class="text-xs text-gray-500">{{ __('A way to reach the family. Parent/guardian records come later.') }}</p>
        <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-input name="contact_email" type="email" :label="__('Contact email')" :value="old('contact_email', $student->contact_email)" />
            <x-input name="contact_phone" :label="__('Contact phone')" :value="old('contact_phone', $student->contact_phone)" />
        </div>
        <div class="mt-4 space-y-4">
            <x-input name="address_line1" :label="__('Address line 1')" :value="old('address_line1', $student->address_line1)" />
            <x-input name="address_line2" :label="__('Address line 2')" :value="old('address_line2', $student->address_line2)" />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="city" :label="__('City')" :value="old('city', $student->city)" />
                <x-input name="state" :label="__('State / Region')" :value="old('state', $student->state)" />
            </div>
        </div>
    </div>

    <div class="space-y-1">
        <label for="notes" class="block text-sm font-medium text-gray-700">{{ __('Notes') }}</label>
        <textarea id="notes" name="notes" rows="3" class="{{ $selectClass }}">{{ old('notes', $student->notes) }}</textarea>
        @error('notes') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
