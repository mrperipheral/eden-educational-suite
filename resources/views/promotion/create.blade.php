@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<x-layouts.authenticated :title="__('Promote students')">
    <div class="max-w-xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('promotion.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Promotion') }}</a>
        </p>

        <x-card :title="__('Step 1 — choose the source class')">
            <p class="mb-4 text-sm text-gray-600">
                {{ __('Pick the session, level and (if used) arm the students are being promoted from. You\'ll choose who to promote and where to on the next step.') }}
            </p>

            <form method="GET" action="{{ route('promotion.roster') }}" class="space-y-4" x-data="{ levelId: '' }">
                <div class="space-y-1">
                    <label for="source_session" class="block text-sm font-medium text-gray-700">{{ __('Session') }}</label>
                    <select id="source_session" name="source_session" class="{{ $selectClass }}" required>
                        <option value="">{{ __('Select a session') }}</option>
                        @foreach ($sessions as $session)
                            <option value="{{ $session->id }}">{{ $session->name }}</option>
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
                    <label for="source_arm" class="block text-sm font-medium text-gray-700">{{ __('Arm (optional — leave blank for the whole level)') }}</label>
                    <select id="source_arm" name="source_arm" class="{{ $selectClass }}">
                        <option value="">{{ __('Whole level') }}</option>
                        @foreach ($levels as $level)
                            @foreach ($level->arms as $arm)
                                <option value="{{ $arm->id }}" x-show="levelId === '{{ $level->id }}'">{{ $level->name }} — {{ $arm->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>

                <x-button type="submit">{{ __('Find eligible students') }}</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
