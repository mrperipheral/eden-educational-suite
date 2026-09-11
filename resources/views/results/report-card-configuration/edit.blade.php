@php
    $selectClass = 'rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $sections = [
        __('Student information') => ['show_student_name' => __('Student name'), 'show_admission_number' => __('Admission number'), 'show_student_photo' => __('Student photo'), 'show_class_level' => __('Class'), 'show_class_arm' => __('Arm'), 'show_session' => __('Session'), 'show_term' => __('Term')],
        __('Academic results') => ['show_subject_components' => __('Component breakdown'), 'show_subject_percentage' => __('Percentage'), 'show_subject_grade' => __('Grade'), 'show_subject_remark' => __('Remark'), 'show_subject_position' => __('Subject position')],
        __('Overall performance') => ['show_overall_total' => __('Total'), 'show_overall_average' => __('Average'), 'show_overall_position' => __('Class position'), 'show_overall_class_size' => __('Class size')],
        __('Attendance') => ['show_attendance_days_opened' => __('Days school opened'), 'show_attendance_days_present' => __('Days present'), 'show_attendance_days_absent' => __('Days absent'), 'show_attendance_percentage' => __('Attendance percentage')],
        __('Comments') => ['show_class_teacher_comment' => __('Class teacher comment'), 'show_principal_comment' => __('Principal comment')],
        __('Signatures') => ['show_class_teacher_signature' => __('Class teacher signature'), 'show_principal_signature' => __('Principal signature')],
    ];
@endphp

<x-layouts.authenticated :title="__('Report card configuration')">
    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <p class="text-sm">
            <a href="{{ route('results.runs.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Results') }}</a>
        </p>

        <x-card :title="__('Scope')">
            <p class="mb-3 text-sm text-gray-600">
                {{ __('Configure the school-wide default, or narrow a configuration to one session (or one session + term). The most specific configuration wins.') }}
            </p>
            <form method="GET" action="{{ route('results.report-card-configuration.edit') }}"
                x-data="{ sessionId: '{{ $sessionId }}' }" class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('Session') }}</label>
                    <select name="session" x-model="sessionId" class="{{ $selectClass }}">
                        <option value="">{{ __('School-wide default') }}</option>
                        @foreach ($sessions as $s)
                            <option value="{{ $s->id }}" @selected($sessionId === $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('Term') }}</label>
                    <select name="period" class="{{ $selectClass }}" :disabled="!sessionId">
                        <option value="">{{ __('Whole session') }}</option>
                        @foreach ($sessions as $s)
                            @foreach ($s->periods as $p)
                                <option value="{{ $p->id }}" x-show="sessionId === '{{ $s->id }}'" @selected($periodId === $p->id)>{{ $p->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary">{{ __('Switch') }}</x-button>
            </form>
        </x-card>

        @can('result.manage')
            <form method="PATCH" action="{{ route('results.report-card-configuration.update') }}" class="space-y-6">
                @csrf
                @method('PATCH')
                <input type="hidden" name="academic_session_id" value="{{ $sessionId }}">
                <input type="hidden" name="academic_period_id" value="{{ $periodId }}">

                <x-card>
                    <x-input name="name" :label="__('Configuration name')" :value="old('name', $configuration->name)" required />
                </x-card>

                @foreach ($sections as $title => $fields)
                    <x-card :title="$title">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach ($fields as $field => $label)
                                <x-checkbox :name="$field" :label="$label" :checked="old($field, $configuration->{$field})" />
                            @endforeach
                        </div>
                    </x-card>
                @endforeach

                <x-button type="submit">{{ __('Save configuration') }}</x-button>
            </form>

            <x-card :title="__('Signatures')">
                <p class="mb-3 text-sm text-gray-600">{{ __('Uploaded here for this exact scope. Images are never used to reconstruct a locked report card\'s historical appearance — only the show/hide choice above is frozen once a run is published.') }}</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p class="mb-1 text-xs font-medium text-gray-500">{{ __('Principal signature') }}</p>
                        @if ($configuration->hasPrincipalSignature())
                            <img src="{{ route('results.report-card-configuration.principal-signature.show', ['session' => $sessionId, 'period' => $periodId]) }}" alt="" class="mb-2 h-16">
                            <form method="POST" action="{{ route('results.report-card-configuration.principal-signature.destroy', ['session' => $sessionId, 'period' => $periodId]) }}">
                                @csrf @method('DELETE')
                                <x-button type="submit" size="sm" variant="ghost">{{ __('Remove') }}</x-button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('results.report-card-configuration.principal-signature.update', ['session' => $sessionId, 'period' => $periodId]) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                                @csrf
                                <input type="file" name="signature" accept="image/*" class="text-xs">
                                <x-button type="submit" size="sm">{{ __('Upload') }}</x-button>
                            </form>
                        @endif
                        @error('signature') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <p class="mb-1 text-xs font-medium text-gray-500">{{ __('Class teacher signature') }}</p>
                        @if ($configuration->hasClassTeacherSignature())
                            <img src="{{ route('results.report-card-configuration.class-teacher-signature.show', ['session' => $sessionId, 'period' => $periodId]) }}" alt="" class="mb-2 h-16">
                            <form method="POST" action="{{ route('results.report-card-configuration.class-teacher-signature.destroy', ['session' => $sessionId, 'period' => $periodId]) }}">
                                @csrf @method('DELETE')
                                <x-button type="submit" size="sm" variant="ghost">{{ __('Remove') }}</x-button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('results.report-card-configuration.class-teacher-signature.update', ['session' => $sessionId, 'period' => $periodId]) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                                @csrf
                                <input type="file" name="signature" accept="image/*" class="text-xs">
                                <x-button type="submit" size="sm">{{ __('Upload') }}</x-button>
                            </form>
                        @endif
                    </div>
                </div>
            </x-card>
        @else
            <x-card :title="__('Fields shown')">
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($sections as $title => $fields)
                        @foreach ($fields as $field => $label)
                            @if ($configuration->{$field})
                                <p class="text-sm text-gray-700">✓ {{ $label }}</p>
                            @endif
                        @endforeach
                    @endforeach
                </div>
            </x-card>
        @endcan
    </div>
</x-layouts.authenticated>
