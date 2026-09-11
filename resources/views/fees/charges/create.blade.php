@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $money = fn ($n) => number_format((float) $n, 2);
@endphp

<x-layouts.authenticated :title="__('Add charge — :name', ['name' => $student->fullName()])">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('fees.students.show', $student) }}" class="text-brand-600 hover:text-brand-700">{{ __('← :name', ['name' => $student->fullName()]) }}</a>
        </p>

        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        @if (! $student->currentEnrollment)
            <x-alert variant="danger">{{ __('This student has no current class enrollment — a charge cannot be raised until they are placed in a class.') }}</x-alert>
        @else
            <x-card :title="__('Add charge')" x-data="{ mode: '{{ old('fee_category_id') ? 'manual' : 'structure' }}' }">
                <form method="POST" action="{{ route('fees.students.charges.store', $student) }}" class="space-y-6">
                    @csrf

                    <div class="flex gap-4 text-sm">
                        <label class="flex items-center gap-2">
                            <input type="radio" x-model="mode" value="structure" class="text-brand-600 focus:ring-brand-500">
                            {{ __('From a fee structure') }}
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" x-model="mode" value="manual" class="text-brand-600 focus:ring-brand-500">
                            {{ __('Manual charge') }}
                        </label>
                    </div>

                    <div x-show="mode === 'structure'" x-cloak class="space-y-1">
                        <label for="fee_structure_id" class="block text-sm font-medium text-gray-700">{{ __('Fee structure') }}</label>
                        @if ($structures->isEmpty())
                            <p class="text-xs text-gray-500">{{ __('No active fee structures apply to this student\'s current class.') }}</p>
                        @else
                            <select id="fee_structure_id" name="fee_structure_id" class="{{ $selectClass }}">
                                @foreach ($structures as $structure)
                                    <option value="{{ $structure->id }}" @selected(old('fee_structure_id') == $structure->id)>
                                        {{ $structure->category?->name }} — {{ $money($structure->amount) }}
                                        ({{ $structure->session?->name }}{{ $structure->period ? ' · '.$structure->period->name : '' }})
                                    </option>
                                @endforeach
                            </select>
                        @endif
                        @error('fee_structure_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div x-show="mode === 'manual'" x-cloak class="space-y-4">
                        <div class="space-y-1">
                            <label for="fee_category_id" class="block text-sm font-medium text-gray-700">{{ __('Category') }}</label>
                            <select id="fee_category_id" name="fee_category_id" class="{{ $selectClass }}">
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('fee_category_id') == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('fee_category_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1">
                                <label for="academic_session_id" class="block text-sm font-medium text-gray-700">{{ __('Session') }}</label>
                                <select id="academic_session_id" name="academic_session_id" class="{{ $selectClass }}">
                                    @foreach ($sessions as $session)
                                        <option value="{{ $session->id }}" @selected(old('academic_session_id') == $session->id)>{{ $session->name }}</option>
                                    @endforeach
                                </select>
                                @error('academic_session_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="space-y-1">
                                <label for="academic_period_id" class="block text-sm font-medium text-gray-700">{{ __('Term (optional)') }}</label>
                                <select id="academic_period_id" name="academic_period_id" class="{{ $selectClass }}">
                                    <option value="">{{ __('Whole session') }}</option>
                                    @foreach ($periods as $period)
                                        <option value="{{ $period->id }}" @selected(old('academic_period_id') == $period->id)>{{ $period->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <x-input name="amount" type="number" step="0.01" min="0.01" :label="__('Amount')" :value="old('amount')" />
                    </div>

                    <x-input name="description" :label="__('Description (optional — defaults to the category name)')" :value="old('description')" />

                    <x-button type="submit">{{ __('Add charge') }}</x-button>
                </form>
            </x-card>
        @endif
    </div>
</x-layouts.authenticated>
