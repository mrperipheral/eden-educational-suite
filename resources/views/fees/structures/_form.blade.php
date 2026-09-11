@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="space-y-1">
            <label for="fee_category_id" class="block text-sm font-medium text-gray-700">{{ __('Category') }}</label>
            <select id="fee_category_id" name="fee_category_id" class="{{ $selectClass }}">
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('fee_category_id', $structure->fee_category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('fee_category_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <x-input name="amount" type="number" step="0.01" min="0.01" :label="__('Amount')" :value="old('amount', $structure->amount)" required />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2" x-data="{ sessionId: '{{ old('academic_session_id', $structure->academic_session_id) }}' }">
        <div class="space-y-1">
            <label for="academic_session_id" class="block text-sm font-medium text-gray-700">{{ __('Session') }}</label>
            <select id="academic_session_id" name="academic_session_id" x-model="sessionId" class="{{ $selectClass }}">
                @foreach ($sessions as $session)
                    <option value="{{ $session->id }}" @selected(old('academic_session_id', $structure->academic_session_id) == $session->id)>{{ $session->name }}</option>
                @endforeach
            </select>
            @error('academic_session_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-1">
            <label for="academic_period_id" class="block text-sm font-medium text-gray-700">{{ __('Term (optional — leave blank for session-wide)') }}</label>
            <select id="academic_period_id" name="academic_period_id" class="{{ $selectClass }}">
                <option value="">{{ __('Whole session') }}</option>
                @foreach ($sessions as $session)
                    @foreach ($session->periods as $period)
                        <option value="{{ $period->id }}" x-show="sessionId === '{{ $session->id }}'" @selected(old('academic_period_id', $structure->academic_period_id) == $period->id)>{{ $period->name }}</option>
                    @endforeach
                @endforeach
            </select>
            @error('academic_period_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2" x-data="{ levelId: '{{ old('academic_level_id', $structure->academic_level_id) }}' }">
        <div class="space-y-1">
            <label for="academic_level_id" class="block text-sm font-medium text-gray-700">{{ __('Level') }}</label>
            <select id="academic_level_id" name="academic_level_id" x-model="levelId" class="{{ $selectClass }}">
                @foreach ($levels as $level)
                    <option value="{{ $level->id }}" @selected(old('academic_level_id', $structure->academic_level_id) == $level->id)>{{ $level->name }}</option>
                @endforeach
            </select>
            @error('academic_level_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-1">
            <label for="level_arm_id" class="block text-sm font-medium text-gray-700">{{ __('Arm (optional — leave blank for the whole level)') }}</label>
            <select id="level_arm_id" name="level_arm_id" class="{{ $selectClass }}">
                <option value="">{{ __('Whole level') }}</option>
                @foreach ($levels as $level)
                    @foreach ($level->arms as $arm)
                        <option value="{{ $arm->id }}" x-show="levelId === '{{ $level->id }}'" @selected(old('level_arm_id', $structure->level_arm_id) == $arm->id)>{{ $level->name }} — {{ $arm->name }}</option>
                    @endforeach
                @endforeach
            </select>
            @error('level_arm_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <x-input name="description" :label="__('Description (optional)')" :value="old('description', $structure->description)" />

    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="is_mandatory" value="1" @checked(old('is_mandatory', $structure->is_mandatory ?? true))
            class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
        {{ __('Mandatory for every applicable student') }}
    </label>

    @if ($structure->exists)
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $structure->is_active))
                class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            {{ __('Active') }}
        </label>
    @endif
</div>
