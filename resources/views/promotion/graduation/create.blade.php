@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $textareaClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<x-layouts.authenticated :title="__('Graduate students')">
    <div class="space-y-6">
        <p class="text-sm">
            <a href="{{ route('promotion.graduation.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Graduation') }}</a>
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

        <x-card :title="__('Step 1 — choose a class to browse')">
            <p class="mb-4 text-sm text-gray-600">
                {{ __('Pick a class to find its currently active students, then choose who to graduate below.') }}
            </p>

            <form method="GET" action="{{ route('promotion.graduation.create') }}" class="flex flex-wrap items-end gap-3" x-data="{ levelId: '{{ $sourceLevel?->id }}' }">
                <div class="space-y-1">
                    <label for="source_session" class="block text-sm font-medium text-gray-700">{{ __('Session') }}</label>
                    <select id="source_session" name="source_session" class="{{ $selectClass }}" required>
                        <option value="">{{ __('Select a session') }}</option>
                        @foreach ($sessions as $session)
                            <option value="{{ $session->id }}" @selected($sourceSession?->id === $session->id)>{{ $session->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label for="source_level" class="block text-sm font-medium text-gray-700">{{ __('Level') }}</label>
                    <select id="source_level" name="source_level" x-model="levelId" class="{{ $selectClass }}" required>
                        <option value="">{{ __('Select a level') }}</option>
                        @foreach ($levels as $level)
                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label for="source_arm" class="block text-sm font-medium text-gray-700">{{ __('Arm (optional)') }}</label>
                    <select id="source_arm" name="source_arm" class="{{ $selectClass }}">
                        <option value="">{{ __('Whole level') }}</option>
                        @foreach ($levels as $level)
                            @foreach ($level->arms as $arm)
                                <option value="{{ $arm->id }}" x-show="levelId === '{{ $level->id }}'" @selected($sourceArm?->id === $arm->id)>{{ $level->name }} — {{ $arm->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>

                <x-button type="submit" variant="secondary">{{ __('Find students') }}</x-button>
            </form>
        </x-card>

        @if ($sourceSession && $sourceLevel)
            @if ($students->isEmpty())
                <x-empty-state
                    :title="__('No active students in this class')"
                    :description="__('There are no active students currently enrolled in this session/level/arm.')"
                />
            @else
                <form method="POST" action="{{ route('promotion.graduation.store') }}"
                    x-data="{
                        selected: { @foreach ($students as $student) {{ $student->id }}: false, @endforeach },
                        allSelected: false,
                        toggleAll() { for (const k in this.selected) { this.selected[k] = this.allSelected } },
                        selectedCount() { return Object.values(this.selected).filter(Boolean).length },
                    }"
                >
                    @csrf

                    <x-card :title="__('Step 2 — graduation details')">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1">
                                <label for="academic_session_id" class="block text-sm font-medium text-gray-700">{{ __('Graduating session') }}</label>
                                <select id="academic_session_id" name="academic_session_id" class="{{ $selectClass }}" required>
                                    @foreach ($sessions as $session)
                                        <option value="{{ $session->id }}" @selected(old('academic_session_id', $sourceSession->id) == $session->id)>{{ $session->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 space-y-1">
                            <label for="notes" class="block text-sm font-medium text-gray-700">{{ __('Notes (optional)') }}</label>
                            <textarea id="notes" name="notes" rows="2" maxlength="255" class="{{ $textareaClass }}">{{ old('notes') }}</textarea>
                        </div>
                    </x-card>

                    <x-card :title="__('Students')" class="mt-6" :padding="false">
                        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 sm:px-6">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                <input type="checkbox" x-model="allSelected" @change="toggleAll()" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                {{ __('Select all') }}
                            </label>
                            <span class="text-xs text-gray-500" x-text="selectedCount() + ' {{ __('selected') }}'"></span>
                        </div>
                        <ul class="divide-y divide-gray-100">
                            @foreach ($students as $student)
                                <li class="flex items-center gap-3 px-4 py-3 sm:px-6">
                                    <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" x-model="selected[{{ $student->id }}]" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-gray-900">{{ $student->displayName() }}</p>
                                        <p class="text-xs text-gray-500 font-mono">{{ $student->admission_number }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </x-card>

                    <div class="mt-6 flex items-center gap-3">
                        <x-button type="submit" variant="danger">{{ __('Graduate selected students') }}</x-button>
                        <span class="text-xs text-gray-500">{{ __('This is a lifecycle change, not a deletion — history is preserved.') }}</span>
                    </div>
                </form>
            @endif
        @endif
    </div>
</x-layouts.authenticated>
