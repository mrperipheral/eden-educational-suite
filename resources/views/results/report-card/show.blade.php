@php
    $fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $c = $configuration;
    $student = $studentResult->student;
@endphp

<x-layouts.authenticated :title="__('Report card — :name', ['name' => $student?->shortName()])">
    <style>
        @media print {
            body * { visibility: hidden; }
            #report-card-print, #report-card-print * { visibility: visible; }
            #report-card-print { position: absolute; inset: 0; width: 100%; padding: 0; }
            .no-print { display: none !important; }
        }
    </style>

    <div class="space-y-4">
        <div class="no-print flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm">
                @can('result.view')
                    <a href="{{ route('results.runs.show', $run->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← Result run') }}</a>
                @elseif (auth()->user()->can('portal.parent'))
                    {{-- Parent Portal viewer (M16) — this view is shared, not duplicated; see docs/parent-portal.md §"Report card reuse". --}}
                    <a href="{{ route('parent.report-cards.index', $studentResult->student_id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← Report cards') }}</a>
                @endif
            </p>
            <x-button type="button" size="sm" x-data x-on:click="window.print()">{{ __('Print') }}</x-button>
        </div>

        <div id="report-card-print" class="mx-auto max-w-3xl bg-white p-8 shadow print:shadow-none" style="max-width: 210mm;">
            <div class="mb-6 flex items-start justify-between border-b border-gray-300 pb-4">
                <div>
                    <p class="text-lg font-semibold text-gray-900">{{ $school->name }}</p>
                    @if ($school->settings?->address_line1)
                        <p class="text-xs text-gray-500">{{ $school->settings->address_line1 }}@if($school->settings->city), {{ $school->settings->city }}@endif</p>
                    @endif
                    @if ($school->settings?->contact_phone || $school->settings?->contact_email)
                        <p class="text-xs text-gray-500">{{ $school->settings->contact_phone }} {{ $school->settings->contact_email }}</p>
                    @endif
                </div>
                @if ($school->settings?->hasLogo() && auth()->user()->can('school.settings.view'))
                    <img src="{{ route('settings.school.branding.logo.show') }}" alt="" class="h-16 w-16 object-contain">
                @endif
            </div>

            <p class="mb-4 text-center text-sm font-semibold uppercase tracking-wide text-gray-700">{{ __('Termly Report Card') }}</p>

            <table class="mb-4 w-full text-sm">
                <tbody>
                    <tr>
                        @if ($c->show_student_name)
                            <td class="py-0.5 pr-2 font-medium text-gray-500">{{ __('Name') }}</td>
                            <td class="py-0.5 pr-4 text-gray-900">{{ $student?->fullName() }}</td>
                        @endif
                        @if ($c->show_admission_number)
                            <td class="py-0.5 pr-2 font-medium text-gray-500">{{ __('Admission No.') }}</td>
                            <td class="py-0.5 text-gray-900">{{ $student?->admission_number }}</td>
                        @endif
                    </tr>
                    <tr>
                        @if ($c->show_class_level)
                            <td class="py-0.5 pr-2 font-medium text-gray-500">{{ __('Class') }}</td>
                            <td class="py-0.5 pr-4 text-gray-900">{{ $run->level?->name }}</td>
                        @endif
                        @if ($c->show_class_arm)
                            <td class="py-0.5 pr-2 font-medium text-gray-500">{{ __('Arm') }}</td>
                            <td class="py-0.5 text-gray-900">{{ $run->arm?->name }}</td>
                        @endif
                    </tr>
                    <tr>
                        @if ($c->show_session)
                            <td class="py-0.5 pr-2 font-medium text-gray-500">{{ __('Session') }}</td>
                            <td class="py-0.5 pr-4 text-gray-900">{{ $run->session?->name }}</td>
                        @endif
                        @if ($c->show_term)
                            <td class="py-0.5 pr-2 font-medium text-gray-500">{{ __('Term') }}</td>
                            <td class="py-0.5 text-gray-900">{{ $run->period?->name }}</td>
                        @endif
                    </tr>
                </tbody>
            </table>

            <table class="mb-4 w-full border-collapse text-xs">
                <thead>
                    <tr class="border-b-2 border-gray-300 text-left">
                        <th class="py-1.5 pr-2">{{ __('Subject') }}</th>
                        @if ($c->show_subject_components) <th class="py-1.5 pr-2">{{ __('Breakdown') }}</th> @endif
                        @if ($c->show_subject_percentage) <th class="py-1.5 pr-2 text-right">{{ __('%') }}</th> @endif
                        @if ($c->show_subject_grade) <th class="py-1.5 pr-2">{{ __('Grade') }}</th> @endif
                        @if ($c->show_subject_remark) <th class="py-1.5 pr-2">{{ __('Remark') }}</th> @endif
                        @if ($c->show_subject_position && $run->ranking_enabled) <th class="py-1.5">{{ __('Position') }}</th> @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subjectResults as $sr)
                        <tr class="border-b border-gray-100">
                            <td class="py-1.5 pr-2 font-medium text-gray-900">{{ $sr->subject?->name }}</td>
                            @if ($c->show_subject_components)
                                <td class="py-1.5 pr-2 text-gray-500">{{ $sr->components->map(fn ($x) => $x->category_name_snapshot.' '.$fmt($x->raw_score).'/'.$fmt($x->raw_max_score))->implode(', ') }}</td>
                            @endif
                            @if ($c->show_subject_percentage) <td class="py-1.5 pr-2 text-right">{{ $fmt($sr->percentage) }}</td> @endif
                            @if ($c->show_subject_grade) <td class="py-1.5 pr-2">{{ $sr->grade_code_snapshot ?? '—' }}</td> @endif
                            @if ($c->show_subject_remark) <td class="py-1.5 pr-2">{{ $sr->grade_remark_snapshot }}</td> @endif
                            @if ($c->show_subject_position && $run->ranking_enabled) <td class="py-1.5">{{ $sr->subject_position ?? '—' }}</td> @endif
                        </tr>
                    @empty
                        <tr><td class="py-2 text-gray-400">{{ __('No subjects recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if ($c->show_overall_total || $c->show_overall_average || $c->show_overall_position || $c->show_overall_class_size)
                <table class="mb-4 w-full text-sm">
                    <tbody>
                        <tr>
                            @if ($c->show_overall_total)
                                <td class="py-0.5 pr-2 font-medium text-gray-500">{{ __('Total') }}</td>
                                <td class="py-0.5 pr-4 text-gray-900">{{ $fmt($studentResult->total_percentage) }}</td>
                            @endif
                            @if ($c->show_overall_average)
                                <td class="py-0.5 pr-2 font-medium text-gray-500">{{ __('Average') }}</td>
                                <td class="py-0.5 text-gray-900">{{ $fmt($studentResult->average_percentage) }}%</td>
                            @endif
                        </tr>
                        @if ($run->ranking_enabled && ($c->show_overall_position || $c->show_overall_class_size))
                            <tr>
                                @if ($c->show_overall_position)
                                    <td class="py-0.5 pr-2 font-medium text-gray-500">{{ __('Class position') }}</td>
                                    <td class="py-0.5 pr-4 text-gray-900">{{ $studentResult->position ?? '—' }}</td>
                                @endif
                                @if ($c->show_overall_class_size)
                                    <td class="py-0.5 pr-2 font-medium text-gray-500">{{ __('Class size') }}</td>
                                    <td class="py-0.5 text-gray-900">{{ $studentResult->class_size }}</td>
                                @endif
                            </tr>
                        @endif
                    </tbody>
                </table>
            @endif

            @if (($c->show_attendance_days_opened || $c->show_attendance_days_present || $c->show_attendance_days_absent || $c->show_attendance_percentage) && $studentResult->hasAttendanceData())
                <table class="mb-4 w-full text-sm">
                    <tbody>
                        <tr>
                            @if ($c->show_attendance_days_opened)
                                <td class="py-0.5 pr-2 font-medium text-gray-500">{{ __('Days opened') }}</td>
                                <td class="py-0.5 pr-4 text-gray-900">{{ $studentResult->days_school_opened }}</td>
                            @endif
                            @if ($c->show_attendance_days_present)
                                <td class="py-0.5 pr-2 font-medium text-gray-500">{{ __('Present') }}</td>
                                <td class="py-0.5 pr-4 text-gray-900">{{ $studentResult->days_present }}</td>
                            @endif
                            @if ($c->show_attendance_days_absent)
                                <td class="py-0.5 pr-2 font-medium text-gray-500">{{ __('Absent') }}</td>
                                <td class="py-0.5 pr-4 text-gray-900">{{ $studentResult->days_absent }}</td>
                            @endif
                            @if ($c->show_attendance_percentage)
                                <td class="py-0.5 pr-2 font-medium text-gray-500">{{ __('Attendance %') }}</td>
                                <td class="py-0.5 text-gray-900">{{ $fmt($studentResult->attendance_percentage) }}%</td>
                            @endif
                        </tr>
                    </tbody>
                </table>
            @endif

            @if (($c->show_class_teacher_comment && $studentResult->class_teacher_comment) || ($c->show_principal_comment && $studentResult->principal_comment))
                <div class="mb-4 space-y-2 text-sm">
                    @if ($c->show_class_teacher_comment && $studentResult->class_teacher_comment)
                        <p><span class="font-medium text-gray-500">{{ __('Class teacher:') }}</span> {{ $studentResult->class_teacher_comment }}</p>
                    @endif
                    @if ($c->show_principal_comment && $studentResult->principal_comment)
                        <p><span class="font-medium text-gray-500">{{ __('Principal:') }}</span> {{ $studentResult->principal_comment }}</p>
                    @endif
                </div>
            @endif

            @if ($c->show_class_teacher_signature || $c->show_principal_signature)
                <div class="mt-8 grid grid-cols-2 gap-8 text-sm">
                    @if ($c->show_class_teacher_signature)
                        <div class="text-center">
                            @if ($signatures->hasClassTeacherSignature())
                                <img src="{{ route('results.report-card-configuration.class-teacher-signature.show', ['session' => $run->academic_session_id, 'period' => $run->academic_period_id]) }}" alt="" class="mx-auto mb-1 h-12">
                            @endif
                            <div class="border-t border-gray-400 pt-1 text-xs text-gray-500">{{ __('Class Teacher') }}</div>
                        </div>
                    @endif
                    @if ($c->show_principal_signature)
                        <div class="text-center">
                            @if ($signatures->hasPrincipalSignature())
                                <img src="{{ route('results.report-card-configuration.principal-signature.show', ['session' => $run->academic_session_id, 'period' => $run->academic_period_id]) }}" alt="" class="mx-auto mb-1 h-12">
                            @endif
                            <div class="border-t border-gray-400 pt-1 text-xs text-gray-500">{{ __('Principal') }}</div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layouts.authenticated>
