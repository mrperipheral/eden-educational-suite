@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';

    $sessionData = $sessions->map(fn ($s) => [
        'id' => $s->id,
        'label' => $s->name.($s->is_current ? ' ('.__('current').')' : ''),
        'periods' => $s->periods->map(fn ($p) => ['id' => $p->id, 'label' => $p->name])->values(),
    ])->values();

    $levelData = $levels->map(fn ($l) => [
        'id' => $l->id,
        'label' => $l->name,
        'arms' => $l->arms->map(fn ($a) => ['id' => $a->id, 'label' => $a->name])->values(),
    ])->values();
@endphp

<x-layouts.authenticated :title="__('New attendance register')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('attendance.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Attendance') }}</a>
        </p>

        <x-card :title="__('Start a register')">
            @if ($sessions->isEmpty() || $levels->isEmpty())
                <x-empty-state
                    :title="__('Academic setup needed')"
                    :description="__('Create an academic session and at least one class before recording attendance.')"
                >
                    <x-slot:actions>
                        <x-button :href="route('academic.sessions.index')" size="sm" variant="secondary">{{ __('Academic setup') }}</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <form method="POST" action="{{ route('attendance.store') }}"
                    x-data="{
                        sessions: {{ Illuminate\Support\Js::from($sessionData) }},
                        levels: {{ Illuminate\Support\Js::from($levelData) }},
                        sessionId: @js((string) old('academic_session_id', '')),
                        periodId: @js((string) old('academic_period_id', '')),
                        levelId: @js((string) old('academic_level_id', '')),
                        armId: @js((string) old('level_arm_id', '')),
                        get periods() { return this.sessions.find(s => String(s.id) === this.sessionId)?.periods ?? []; },
                        get arms() { return this.levels.find(l => String(l.id) === this.levelId)?.arms ?? []; },
                    }"
                    x-effect="if (!periods.some(p => String(p.id) === periodId)) periodId = ''; if (!arms.some(a => String(a.id) === armId)) armId = ''"
                    class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label for="academic_session_id" class="block text-sm font-medium text-gray-700">{{ __('Academic session') }} <span class="text-red-500">*</span></label>
                            <select id="academic_session_id" name="academic_session_id" x-model="sessionId" class="{{ $selectClass }}">
                                <option value="">{{ __('Select a session') }}</option>
                                <template x-for="s in sessions" :key="s.id"><option :value="s.id" x-text="s.label"></option></template>
                            </select>
                            @error('academic_session_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-1">
                            <label for="academic_period_id" class="block text-sm font-medium text-gray-700">{{ __('Term / period') }}</label>
                            <select id="academic_period_id" name="academic_period_id" x-model="periodId" class="{{ $selectClass }}" :disabled="!periods.length">
                                <option value="">{{ __('— Whole session —') }}</option>
                                <template x-for="p in periods" :key="p.id"><option :value="p.id" x-text="p.label"></option></template>
                            </select>
                            @error('academic_period_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label for="academic_level_id" class="block text-sm font-medium text-gray-700">{{ __('Level') }} <span class="text-red-500">*</span></label>
                            <select id="academic_level_id" name="academic_level_id" x-model="levelId" class="{{ $selectClass }}">
                                <option value="">{{ __('Select a level') }}</option>
                                <template x-for="l in levels" :key="l.id"><option :value="l.id" x-text="l.label"></option></template>
                            </select>
                            @error('academic_level_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-1">
                            <label for="level_arm_id" class="block text-sm font-medium text-gray-700">{{ __('Class / arm') }} <span class="text-red-500">*</span></label>
                            <select id="level_arm_id" name="level_arm_id" x-model="armId" class="{{ $selectClass }}" :disabled="!arms.length">
                                <option value="">{{ __('Select an arm') }}</option>
                                <template x-for="a in arms" :key="a.id"><option :value="a.id" x-text="a.label"></option></template>
                            </select>
                            @error('level_arm_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="sm:w-1/2">
                        <x-input name="attendance_date" type="date" :label="__('Date')"
                            :value="old('attendance_date', $register->attendance_date?->toDateString())"
                            :max="now()->toDateString()" required />
                    </div>

                    <x-input name="notes" :label="__('Notes (optional)')" :value="old('notes')"
                        placeholder="{{ __('e.g. Half day — sports') }}" />

                    <div class="flex items-center gap-2 pt-2">
                        <x-button type="submit">{{ __('Create register') }}</x-button>
                        <x-button :href="route('attendance.index')" variant="ghost">{{ __('Cancel') }}</x-button>
                    </div>
                    <p class="text-xs text-gray-400">{{ __('The class roster on the register date is captured when you create it.') }}</p>
                </form>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
