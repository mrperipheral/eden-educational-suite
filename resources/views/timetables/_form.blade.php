@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';

    $sessionData = $sessions->map(fn ($s) => [
        'id' => $s->id,
        'label' => $s->name.($s->is_current ? ' ('.__('current').')' : ''),
        'periods' => $s->periods->map(fn ($p) => ['id' => $p->id, 'label' => $p->name])->values(),
    ])->values();

    $creating = ! $timetable->exists;
    $lockedSessionId = $creating ? null : $timetable->academic_session_id;
    $oldSession = old('academic_session_id', $timetable->academic_session_id);
    $oldPeriod = old('academic_period_id', $timetable->academic_period_id);
@endphp

<div
    class="space-y-4"
    x-data="{
        sessions: {{ Illuminate\Support\Js::from($sessionData) }},
        sessionId: @js((string) ($oldSession ?? ($lockedSessionId ?? ''))),
        periodId: @js((string) ($oldPeriod ?? '')),
        get periods() { return this.sessions.find(s => String(s.id) === this.sessionId)?.periods ?? []; },
    }"
    x-effect="if (!periods.some(p => String(p.id) === periodId)) periodId = ''"
>
    <x-input name="name" :label="__('Name')" :value="old('name', $timetable->name)" required
        placeholder="{{ __('e.g. First Term 2025/26') }}" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="space-y-1">
            <label for="academic_session_id" class="block text-sm font-medium text-gray-700">{{ __('Academic session') }} <span class="text-red-500">*</span></label>
            @if ($creating)
                <select id="academic_session_id" name="academic_session_id" x-model="sessionId" class="{{ $selectClass }}">
                    <option value="">{{ __('Select a session') }}</option>
                    <template x-for="s in sessions" :key="s.id">
                        <option :value="s.id" x-text="s.label"></option>
                    </template>
                </select>
                @error('academic_session_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            @else
                <p class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $timetable->session->name }}</p>
                <p class="text-xs text-gray-400">{{ __('A timetable\'s session is fixed once it exists.') }}</p>
            @endif
        </div>

        <div class="space-y-1">
            <label for="academic_period_id" class="block text-sm font-medium text-gray-700">{{ __('Term / period') }}</label>
            <select id="academic_period_id" name="academic_period_id" x-model="periodId" class="{{ $selectClass }}" :disabled="!periods.length">
                <option value="">{{ __('— Whole session —') }}</option>
                <template x-for="p in periods" :key="p.id">
                    <option :value="p.id" x-text="p.label"></option>
                </template>
            </select>
            @error('academic_period_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
