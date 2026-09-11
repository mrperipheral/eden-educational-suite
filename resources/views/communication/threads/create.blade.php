@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $textareaClass = $selectClass;
@endphp

<x-layouts.authenticated :title="__('New communication thread')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('communication.threads.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Communication Hub') }}</a>
        </p>

        <x-card :title="__('Log communication')">
            <form method="POST" action="{{ route('communication.threads.store') }}" class="space-y-6">
                @csrf

                <x-input name="subject" :label="__('Subject')" :value="old('subject')" required />

                <div class="space-y-1">
                    <label for="category" class="block text-sm font-medium text-gray-700">{{ __('Category') }}</label>
                    <select id="category" name="category" class="{{ $selectClass }}">
                        @foreach ($categories as $c)
                            <option value="{{ $c->value }}" @selected(old('category') === $c->value)>{{ $c->label() }}</option>
                        @endforeach
                    </select>
                    @error('category') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label for="student_id" class="block text-sm font-medium text-gray-700">{{ __('Student (optional)') }}</label>
                        <select id="student_id" name="student_id" class="{{ $selectClass }}">
                            <option value="">{{ __('None') }}</option>
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}" @selected((string) old('student_id') === (string) $student->id)>{{ $student->displayName() }}</option>
                            @endforeach
                        </select>
                        @error('student_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="space-y-1">
                        <label for="guardian_id" class="block text-sm font-medium text-gray-700">{{ __('Guardian (optional)') }}</label>
                        <select id="guardian_id" name="guardian_id" class="{{ $selectClass }}">
                            <option value="">{{ __('None') }}</option>
                            @foreach ($guardians as $guardian)
                                <option value="{{ $guardian->id }}" @selected((string) old('guardian_id') === (string) $guardian->id)>{{ $guardian->fullName() }}</option>
                            @endforeach
                        </select>
                        @error('guardian_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="space-y-1">
                    <label for="body" class="block text-sm font-medium text-gray-700">{{ __('Message') }}</label>
                    <textarea id="body" name="body" rows="5" class="{{ $textareaClass }}" required>{{ old('body') }}</textarea>
                    @error('body') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-2">
                    <x-button type="submit">{{ __('Log communication') }}</x-button>
                    <x-button :href="route('communication.threads.index')" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
