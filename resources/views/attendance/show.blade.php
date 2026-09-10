@php
    $locked = $register->isLocked();
    $editable = ! $locked && $canRecord;
    $inputClass = 'w-full rounded-md border-0 px-2 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';

    $marks = $records->mapWithKeys(fn ($r) => [(string) $r->student_id => $r->status?->value ?? ''])->all();
@endphp

<x-layouts.authenticated :title="__(':class — :date', ['class' => $register->level?->name.' '.$register->arm?->name, 'date' => $register->attendance_date->toFormattedDateString()])">
    <x-slot:actions>
        @if ($locked && $canManage)
            <form method="POST" action="{{ route('attendance.reopen', $register->id) }}">
                @csrf
                <x-button type="submit" size="sm" variant="secondary">{{ __('Reopen for correction') }}</x-button>
            </form>
        @endif
    </x-slot:actions>

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        <p class="text-sm">
            <a href="{{ route('attendance.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Attendance') }}</a>
        </p>

        {{-- Summary --}}
        <x-card>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm text-gray-900">
                        <span class="font-medium">{{ $register->level?->name }} — {{ $register->arm?->name }}</span>
                        · {{ $register->session?->name }}{{ $register->period ? ' · '.$register->period->name : '' }}
                        <x-badge :variant="$register->status->badgeVariant()" class="ml-1">{{ $register->status->label() }}</x-badge>
                    </p>
                    <p class="text-xs text-gray-500">
                        {{ $register->attendance_date->toFormattedDateString() }}
                        @if ($locked && $register->submittedBy)
                            · {{ __('submitted by :name', ['name' => $register->submittedBy->name]) }}
                            @if ($register->submitted_at) {{ $register->submitted_at->diffForHumans() }} @endif
                        @endif
                    </p>
                    @if ($register->notes)
                        <p class="mt-1 text-xs text-gray-600">{{ $register->notes }}</p>
                    @endif
                </div>
                @if (! $locked && $canRecord && $records->isEmpty())
                    <x-confirm :action="route('attendance.destroy', $register->id)" method="DELETE" size="sm"
                        :confirm="__('Delete')" :title="__('Delete register?')"
                        :message="__('This deletes the empty register.')">
                        {{ __('Delete') }}
                    </x-confirm>
                @endif
            </div>
            <div class="mt-3">
                @include('attendance._summary')
            </div>
        </x-card>

        @if ($records->isEmpty())
            <x-empty-state
                :title="__('No students on this register')"
                :description="__('No students were enrolled in this class on the register date.')"
            />
        @elseif ($editable)
            {{-- The taking screen --}}
            <form method="POST" action="{{ route('attendance.records', $register->id) }}"
                x-data="{
                    marks: {{ Illuminate\Support\Js::from($marks) }},
                    get unmarked() { return Object.values(this.marks).filter(m => !m).length; },
                    countOf(s) { return Object.values(this.marks).filter(m => m === s).length; },
                    all(s) { for (const k in this.marks) this.marks[k] = s; },
                }">
                @csrf
                @method('PATCH')

                <x-card>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap gap-1.5 text-xs">
                            <x-button type="button" size="sm" variant="secondary" x-on:click="all('present')">{{ __('Mark all present') }}</x-button>
                            <x-button type="button" size="sm" variant="ghost" x-on:click="all('')">{{ __('Clear all') }}</x-button>
                        </div>
                        <p class="text-xs text-gray-500">
                            <span x-text="countOf('present') + countOf('late')"></span> {{ __('in') }} ·
                            <span x-text="unmarked" :class="unmarked > 0 ? 'font-semibold text-amber-600' : ''"></span> {{ __('unmarked') }}
                        </p>
                    </div>

                    <ul class="mt-3 divide-y divide-gray-100">
                        @foreach ($records as $record)
                            @php($sid = (string) $record->student_id)
                            <li class="py-3 first:pt-0 last:pb-0">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $record->student?->displayName() }} {{ $record->student?->last_name }}
                                            @if ($record->student && $record->student->status->value !== 'active')
                                                <x-badge :variant="$record->student->status->badgeVariant()" class="ml-1">{{ $record->student->status->label() }}</x-badge>
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-500"><span class="font-mono">{{ $record->student?->admission_number }}</span></p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1" role="group" aria-label="{{ __('Attendance status') }}">
                                        <input type="hidden" name="records[{{ $sid }}][status]" x-model="marks['{{ $sid }}']">
                                        @foreach ($statuses as $s)
                                            <button type="button"
                                                x-on:click="marks['{{ $sid }}'] = '{{ $s->value }}'"
                                                :aria-pressed="marks['{{ $sid }}'] === '{{ $s->value }}'"
                                                :class="marks['{{ $sid }}'] === '{{ $s->value }}'
                                                    ? 'bg-brand-600 text-white ring-brand-600'
                                                    : 'bg-white text-gray-600 ring-gray-300 hover:bg-gray-50'"
                                                class="rounded-md px-2.5 py-1.5 text-xs font-semibold ring-1 ring-inset">{{ mb_substr($s->label(), 0, 1) }}</button>
                                        @endforeach
                                    </div>
                                </div>
                                <input type="text" name="records[{{ $sid }}][note]" value="{{ $record->note }}"
                                    maxlength="255" placeholder="{{ __('Note / reason (optional)') }}"
                                    class="{{ $inputClass }} mt-2">
                            </li>
                        @endforeach
                    </ul>
                </x-card>

                <div class="sticky bottom-0 mt-4 flex flex-wrap items-center gap-2 border-t border-gray-200 bg-gray-50 py-3">
                    <x-button type="submit">{{ __('Save draft') }}</x-button>
                    <x-button type="submit" name="submit" value="1" variant="secondary"
                        x-bind:disabled="unmarked > 0"
                        x-bind:title="unmarked > 0 ? '{{ __('Mark every student first') }}' : ''">
                        {{ __('Save & submit') }}
                    </x-button>
                    @error('records') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </form>
        @else
            {{-- Read-only roster (locked, or viewer without record permission) --}}
            <x-card :title="__('Roster')">
                <ul class="divide-y divide-gray-100">
                    @foreach ($records as $record)
                        <li class="flex flex-col gap-1 py-2.5 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm text-gray-900">{{ $record->student?->displayName() }} {{ $record->student?->last_name }}
                                    <span class="font-mono text-xs text-gray-400">{{ $record->student?->admission_number }}</span>
                                </p>
                                @if ($record->note)
                                    <p class="text-xs text-gray-500">{{ $record->note }}</p>
                                @endif
                            </div>
                            <div class="shrink-0">
                                @if ($record->status)
                                    <x-badge :variant="$record->status->badgeVariant()">{{ $record->status->label() }}</x-badge>
                                @else
                                    <span class="text-xs text-gray-400">{{ __('unmarked') }}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if ($locked && $canManage)
                    <p class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-400">
                        {{ __('This register is locked. Use "Reopen for correction" to change any mark.') }}
                    </p>
                @endif
            </x-card>
        @endif
    </div>
</x-layouts.authenticated>
