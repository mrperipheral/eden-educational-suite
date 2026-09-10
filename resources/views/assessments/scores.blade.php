@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $max = (float) $assessment->max_score;
    $rows = $scores->map(fn ($s) => [
        'sid' => (string) $s->student_id,
        'score' => $s->score === null ? '' : $fmt($s->score),
        'comment' => (string) $s->comment,
    ]);
    $inputClass = 'w-full rounded-md border-0 px-2 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<x-layouts.authenticated :title="__('Score sheet — :title', ['title' => $assessment->title])">
    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        <p class="text-sm">
            <a href="{{ route('assessments.show', $assessment->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← Back to assessment') }}</a>
        </p>

        <x-card>
            <p class="text-sm text-gray-900">
                <span class="font-medium">{{ $assessment->title }}</span>
                <x-badge :variant="$assessment->status->badgeVariant()" class="ml-1">{{ $assessment->status->label() }}</x-badge>
            </p>
            <p class="text-xs text-gray-500">
                {{ $assessment->level?->name }} — {{ $assessment->arm?->name }} · {{ $assessment->subject?->name }} ·
                {{ $assessment->category?->name }} · {{ __('out of :n', ['n' => $fmt($assessment->max_score)]) }}
            </p>
            <div class="mt-3">@include('assessments._summary')</div>

            @if ($assessment->isDraft())
                <form method="POST" action="{{ route('assessments.scores.sync', $assessment->id) }}" class="mt-3">
                    @csrf
                    <x-button type="submit" size="sm" variant="ghost">{{ __('Sync roster with current enrolment') }}</x-button>
                </form>
            @endif
        </x-card>

        @if ($scores->isEmpty())
            <x-empty-state :title="__('No students on this assessment')"
                :description="__('No students were enrolled in this class on the assessment date.')" />
        @else
            <form method="POST" action="{{ route('assessments.scores.update', $assessment->id) }}"
                x-data="{
                    max: {{ $max }},
                    rows: {{ Illuminate\Support\Js::from($rows) }},
                    invalid(v) { if (v === '' || v === null) return false; const n = Number(v); return isNaN(n) || n < 0 || n > this.max; },
                    get entered() { return this.rows.filter(r => r.score !== '' && !this.invalid(r.score)).length; },
                    get bad() { return this.rows.filter(r => this.invalid(r.score)).length; },
                }">
                @csrf
                @method('PATCH')

                <x-card :padding="false">
                    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 sm:px-6">
                        <p class="text-xs text-gray-500">
                            <span x-text="entered"></span> {{ __('entered') }} ·
                            <span x-text="bad" :class="bad > 0 ? 'font-semibold text-red-600' : ''"></span> {{ __('out of range') }}
                        </p>
                        <p class="text-xs text-gray-400">{{ __('Leave blank for a student not scored yet.') }}</p>
                    </div>

                    <ul class="divide-y divide-gray-100">
                        @foreach ($scores as $row)
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
                                    <div class="flex shrink-0 items-center gap-2">
                                        <input type="number" step="0.01" min="0" :max="max"
                                            name="scores[{{ $sid }}][score]" x-model="rows[i].score"
                                            :class="rows[i] && invalid(rows[i].score) ? 'ring-red-400' : 'ring-gray-300'"
                                            class="w-24 rounded-md border-0 px-2 py-1.5 text-right text-sm text-gray-900 ring-1 ring-inset focus:ring-2 focus:ring-inset focus:ring-brand-500"
                                            aria-label="{{ __('Score for :name', ['name' => $row->student?->shortName()]) }}"
                                            inputmode="decimal">
                                        <span class="text-xs text-gray-400">/ {{ $fmt($assessment->max_score) }}</span>
                                    </div>
                                </div>
                                <input type="text" name="scores[{{ $sid }}][comment]" value="{{ $row->comment }}"
                                    maxlength="500" placeholder="{{ __('Comment (optional)') }}" class="{{ $inputClass }} mt-2">
                                @error('scores.'.$sid.'.score') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </li>
                        @endforeach
                    </ul>
                </x-card>

                <div class="sticky bottom-0 mt-4 flex flex-wrap items-center gap-2 border-t border-gray-200 bg-gray-50 py-3">
                    <x-button type="submit" x-bind:disabled="bad > 0">{{ __('Save scores') }}</x-button>
                    <span class="text-xs text-red-600" x-show="bad > 0">{{ __('Fix the highlighted scores first.') }}</span>
                    @error('scores') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </form>
        @endif
    </div>
</x-layouts.authenticated>
