@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $textareaClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<x-layouts.authenticated :title="__('Promote students')">
    <div class="space-y-6">
        <p class="text-sm">
            <a href="{{ route('promotion.create') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Choose a different source class') }}</a>
        </p>

        @if ($errors->any())
            <x-alert variant="danger" :title="__('Please fix the following')">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <x-card>
            <p class="text-sm text-gray-600">
                {{ __('Promoting from') }}
                <span class="font-medium text-gray-900">
                    {{ $sourceLevel->name }}{{ $sourceArm ? ' — '.$sourceArm->name : '' }}, {{ $sourceSession->name }}
                </span>
            </p>
        </x-card>

        @if ($students->isEmpty())
            <x-empty-state
                :title="__('No eligible students in this class')"
                :description="__('There are no active students currently enrolled in this session/level/arm.')"
            />
        @else
            <form method="POST" action="{{ route('promotion.store') }}"
                x-data="{
                    selected: { @foreach ($students as $student) {{ $student->id }}: false, @endforeach },
                    allSelected: false,
                    targetLevelId: '{{ old('target_academic_level_id', '') }}',
                    toggleAll() { for (const k in this.selected) { this.selected[k] = this.allSelected } },
                    selectedCount() { return Object.values(this.selected).filter(Boolean).length },
                }"
            >
                @csrf
                <input type="hidden" name="source_academic_session_id" value="{{ $sourceSession->id }}">
                <input type="hidden" name="source_academic_level_id" value="{{ $sourceLevel->id }}">
                @if ($sourceArm)
                    <input type="hidden" name="source_level_arm_id" value="{{ $sourceArm->id }}">
                @endif

                <x-card :title="__('Target class')">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="space-y-1">
                            <label for="target_academic_session_id" class="block text-sm font-medium text-gray-700">{{ __('Session') }}</label>
                            <select id="target_academic_session_id" name="target_academic_session_id" class="{{ $selectClass }}" required>
                                <option value="">{{ __('Select a session') }}</option>
                                @foreach ($sessions as $session)
                                    <option value="{{ $session->id }}" @selected(old('target_academic_session_id') == $session->id)>{{ $session->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label for="target_academic_level_id" class="block text-sm font-medium text-gray-700">{{ __('Level') }}</label>
                            <select id="target_academic_level_id" name="target_academic_level_id" x-model="targetLevelId" class="{{ $selectClass }}" required>
                                <option value="">{{ __('Select a level') }}</option>
                                @foreach ($levels as $level)
                                    <option value="{{ $level->id }}">{{ $level->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label for="target_level_arm_id" class="block text-sm font-medium text-gray-700">{{ __('Arm (optional)') }}</label>
                            <select id="target_level_arm_id" name="target_level_arm_id" class="{{ $selectClass }}">
                                <option value="">{{ __('Whole level') }}</option>
                                @foreach ($levels as $level)
                                    @foreach ($level->arms as $arm)
                                        <option value="{{ $arm->id }}" x-show="targetLevelId === '{{ $level->id }}'" @selected(old('target_level_arm_id') == $arm->id)>{{ $level->name }} — {{ $arm->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 space-y-1">
                        <label for="notes" class="block text-sm font-medium text-gray-700">{{ __('Notes (optional)') }}</label>
                        <textarea id="notes" name="notes" rows="2" class="{{ $textareaClass }}">{{ old('notes') }}</textarea>
                    </div>
                </x-card>

                <x-card :title="__('Eligible students')" class="mt-6" :padding="false">
                    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 sm:px-6">
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input type="checkbox" x-model="allSelected" @change="toggleAll()" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                            {{ __('Select all') }}
                        </label>
                        <span class="text-xs text-gray-500" x-text="selectedCount() + ' {{ __('selected') }}'"></span>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($students as $student)
                            @php($placement = $student->currentEnrollment)
                            <li class="flex items-center gap-3 px-4 py-3 sm:px-6">
                                <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" x-model="selected[{{ $student->id }}]" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $student->displayName() }}</p>
                                    <p class="text-xs text-gray-500">
                                        <span class="font-mono">{{ $student->admission_number }}</span>
                                        @if ($placement)
                                            · {{ $placement->level?->name }}{{ $placement->arm ? ' — '.$placement->arm->name : '' }}
                                        @endif
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-card>

                <div class="mt-6 flex items-center gap-3">
                    <x-button type="submit">{{ __('Promote selected students') }}</x-button>
                    <span class="text-xs text-gray-500">{{ __('You will be shown a success/failure summary after promoting.') }}</span>
                </div>
            </form>
        @endif
    </div>
</x-layouts.authenticated>
