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

    $ready = $sessions->isNotEmpty() && $levels->isNotEmpty() && $categories->isNotEmpty();
@endphp

<x-layouts.authenticated :title="__('New assessment')">
    <div class="max-w-3xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('assessments.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Assessments') }}</a>
        </p>

        <x-card :title="__('Assessment details')">
            @unless ($ready)
                <x-empty-state
                    :title="__('Setup needed')"
                    :description="__('Create an academic session, at least one class with subjects, and one assessment category first.')"
                >
                    <x-slot:actions>
                        <x-button :href="route('assessments.categories.index')" size="sm" variant="secondary">{{ __('Manage categories') }}</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <form method="POST" action="{{ route('assessments.store') }}"
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

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label for="assessment_category_id" class="block text-sm font-medium text-gray-700">{{ __('Category') }} <span class="text-red-500">*</span></label>
                            <select id="assessment_category_id" name="assessment_category_id"
                                class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                <option value="">{{ __('Select a category') }}</option>
                                @foreach ($categories->where('is_active', true) as $category)
                                    <option value="{{ $category->id }}" @selected(old('assessment_category_id') == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('assessment_category_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <x-input name="max_score" type="number" step="0.01" min="0.01" :label="__('Maximum score')"
                            :value="old('max_score', 20)" required />
                    </div>

                    <x-input name="title" :label="__('Title')" :value="old('title')"
                        placeholder="{{ __('e.g. Week 5 Class Test') }}" required />

                    <div class="sm:w-1/2">
                        <x-input name="assessment_date" type="date" :label="__('Assessment date')"
                            :value="old('assessment_date', $assessment->assessment_date?->toDateString())" required />
                    </div>

                    <div class="space-y-1">
                        <label for="instructions" class="block text-sm font-medium text-gray-700">{{ __('Instructions (optional)') }}</label>
                        <textarea id="instructions" name="instructions" rows="3"
                            class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('instructions') }}</textarea>
                        @error('instructions') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <x-button type="submit">{{ __('Create assessment') }}</x-button>
                        <x-button :href="route('assessments.index')" variant="ghost">{{ __('Cancel') }}</x-button>
                    </div>
                    <p class="text-xs text-gray-400">{{ __('The class roster on the assessment date is captured when you create it. The academic context is then fixed.') }}</p>
                </form>
            @endunless
        </x-card>
    </div>
</x-layouts.authenticated>
