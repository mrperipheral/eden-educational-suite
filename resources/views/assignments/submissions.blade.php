@php
    $statuses = \App\Enums\AssignmentSubmissionStatus::all();
    $rows = $submissions->map(fn ($s) => [
        'sid' => (string) $s->student_id,
        'status' => $s->status->value,
        'submitted_on' => $s->submitted_on?->toDateString() ?? '',
        'remark' => (string) $s->remark,
    ]);
    $inputClass = 'w-full rounded-md border-0 px-2 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<x-layouts.authenticated :title="__('Completion — :title', ['title' => $assignment->title])">
    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        <p class="text-sm">
            <a href="{{ route('assessments.assignments.show', $assignment->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← Back to assignment') }}</a>
        </p>

        <x-card>
            <p class="text-sm text-gray-900">
                <span class="font-medium">{{ $assignment->title }}</span>
                <x-badge :variant="$assignment->status->badgeVariant()" class="ml-1">{{ $assignment->status->label() }}</x-badge>
            </p>
            <p class="text-xs text-gray-500">
                {{ $assignment->level?->name }} — {{ $assignment->arm?->name }} · {{ $assignment->subject?->name }} ·
                {{ __('due :date', ['date' => $assignment->due_on->toFormattedDateString()]) }}
            </p>
        </x-card>

        @if ($submissions->isEmpty())
            <x-empty-state :title="__('No students on this assignment')"
                :description="__('No students were enrolled in this class on the assigned date.')" />
        @else
            <form method="POST" action="{{ route('assessments.assignments.submissions.update', $assignment->id) }}"
                x-data="{
                    rows: {{ Illuminate\Support\Js::from($rows) }},
                    all(s) { this.rows.forEach(r => r.status = s); },
                }">
                @csrf
                @method('PATCH')

                <x-card :padding="false">
                    <div class="flex flex-wrap items-center gap-1.5 px-4 py-3 text-xs sm:px-6">
                        <span class="text-gray-500">{{ __('Set all:') }}</span>
                        @foreach ($statuses as $s)
                            <x-button type="button" size="sm" variant="ghost" x-on:click="all('{{ $s->value }}')">{{ $s->label() }}</x-button>
                        @endforeach
                    </div>

                    <ul class="divide-y divide-gray-100">
                        @foreach ($submissions as $row)
                            @php($sid = (string) $row->student_id)
                            <li class="px-4 py-3 sm:px-6" x-data="{ i: {{ $loop->index }} }">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $row->student?->displayName() }} {{ $row->student?->last_name }}
                                            @if ($row->student && $row->student->status->value !== 'active')
                                                <x-badge :variant="$row->student->status->badgeVariant()" class="ml-1">{{ $row->student->status->label() }}</x-badge>
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-500"><span class="font-mono">{{ $row->student?->admission_number }}</span></p>
                                    </div>
                                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                                        <select name="submissions[{{ $sid }}][status]" x-model="rows[i].status"
                                            class="rounded-md border-0 px-2 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                            @foreach ($statuses as $s)
                                                <option value="{{ $s->value }}">{{ $s->label() }}</option>
                                            @endforeach
                                        </select>
                                        <input type="date" name="submissions[{{ $sid }}][submitted_on]" x-model="rows[i].submitted_on"
                                            max="{{ now()->toDateString() }}"
                                            class="rounded-md border-0 px-2 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500"
                                            aria-label="{{ __('Submitted on') }}">
                                    </div>
                                </div>
                                <input type="text" name="submissions[{{ $sid }}][remark]" value="{{ $row->remark }}"
                                    maxlength="500" placeholder="{{ __('Remark (optional)') }}" class="{{ $inputClass }} mt-2">
                                @error('submissions.'.$sid.'.status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                @error('submissions.'.$sid.'.submitted_on') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </li>
                        @endforeach
                    </ul>
                </x-card>

                <div class="sticky bottom-0 mt-4 flex flex-wrap items-center gap-2 border-t border-gray-200 bg-gray-50 py-3">
                    <x-button type="submit">{{ __('Save completion') }}</x-button>
                    @error('submissions') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </form>
        @endif
    </div>
</x-layouts.authenticated>
