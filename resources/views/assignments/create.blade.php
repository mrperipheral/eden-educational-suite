@php
    $sessionData = $sessions->map(fn ($s) => [
        'id' => $s->id,
        'label' => $s->name.($s->is_current ? ' ('.__('current').')' : ''),
        'periods' => $s->periods->map(fn ($p) => ['id' => $p->id, 'label' => $p->name])->values(),
    ])->values();

    $levelData = $levels->map(fn ($l) => [
        'id' => $l->id,
        'label' => $l->name,
        'arms' => $l->arms->map(fn ($a) => ['id' => $a->id, 'label' => $a->name])->values(),
        'subjects' => $l->subjects->map(fn ($s) => ['id' => $s->id, 'label' => $s->name])->values(),
    ])->values();

    $ready = $sessions->isNotEmpty() && $levels->isNotEmpty();
@endphp

<x-layouts.authenticated :title="__('New assignment')">
    <div class="max-w-3xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('assessments.assignments.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Assignments') }}</a>
        </p>

        <x-card :title="__('Assignment details')">
            @unless ($ready)
                <x-empty-state :title="__('Setup needed')"
                    :description="__('Create an academic session and at least one class with subjects first.')" />
            @else
                <form method="POST" action="{{ route('assessments.assignments.store') }}"
                    x-data="{
                        sessions: {{ Illuminate\Support\Js::from($sessionData) }},
                        levels: {{ Illuminate\Support\Js::from($levelData) }},
                        sessionId: @js((string) old('academic_session_id', '')),
                        periodId: @js((string) old('academic_period_id', '')),
                        levelId: @js((string) old('academic_level_id', '')),
                        armId: @js((string) old('level_arm_id', '')),
                        subjectId: @js((string) old('subject_id', '')),
                        get periods() { return this.sessions.find(s => String(s.id) === this.sessionId)?.periods ?? []; },
                        get arms() { return this.levels.find(l => String(l.id) === this.levelId)?.arms ?? []; },
                        get subjects() { return this.levels.find(l => String(l.id) === this.levelId)?.subjects ?? []; },
                    }"
                    x-effect="
                        if (!periods.some(p => String(p.id) === periodId)) periodId = '';
                        if (!arms.some(a => String(a.id) === armId)) armId = '';
                        if (!subjects.some(s => String(s.id) === subjectId)) subjectId = '';
                    "
                    class="space-y-4">
                    @csrf

                    @include('assessments._context-fields')

                    <x-input name="title" :label="__('Title')" :value="old('title')"
                        placeholder="{{ __('e.g. Fractions worksheet') }}" required />

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-input name="assigned_on" type="date" :label="__('Assigned on')"
                            :value="old('assigned_on', $assignment->assigned_on?->toDateString())" required />
                        <x-input name="due_on" type="date" :label="__('Due on')"
                            :value="old('due_on', $assignment->due_on?->toDateString())" required />
                        <x-input name="max_score" type="number" step="0.01" min="0.01" :label="__('Max marks (optional)')"
                            :value="old('max_score')" />
                    </div>

                    <div class="space-y-1">
                        <label for="instructions" class="block text-sm font-medium text-gray-700">{{ __('Instructions (optional)') }}</label>
                        <textarea id="instructions" name="instructions" rows="4"
                            class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('instructions') }}</textarea>
                        @error('instructions') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <x-button type="submit">{{ __('Create assignment') }}</x-button>
                        <x-button :href="route('assessments.assignments.index')" variant="ghost">{{ __('Cancel') }}</x-button>
                    </div>
                    <p class="text-xs text-gray-400">{{ __('The class roster on the assigned date is captured when you create it. It is created as a draft.') }}</p>
                </form>
            @endunless
        </x-card>
    </div>
</x-layouts.authenticated>
