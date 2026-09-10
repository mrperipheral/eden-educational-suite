@php
    $active = $teacher->assignments->where('status', \App\Enums\TeacherAssignmentStatus::Active);
    $past = $teacher->assignments->where('status', '!==', \App\Enums\TeacherAssignmentStatus::Active);
@endphp

<x-layouts.authenticated :title="$teacher->fullName()">
    @can('staff.manage')
        <x-slot:actions>
            <x-button :href="route('teachers.edit', $teacher->id)" size="sm" variant="secondary">{{ __('Edit details') }}</x-button>
        </x-slot:actions>
    @endcan

    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <p class="text-sm">
            <a href="{{ route('teachers.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Teachers') }}</a>
        </p>

        {{-- Identity & employment --}}
        <x-card :title="__('Teacher')">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                <dt class="text-gray-500">{{ __('Name') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">
                    {{ $teacher->fullName() }}
                    @if ($teacher->preferred_name)
                        <span class="text-gray-400">({{ $teacher->preferred_name }})</span>
                    @endif
                </dd>

                <dt class="text-gray-500">{{ __('Employee number') }}</dt>
                <dd class="text-gray-900 sm:col-span-2"><span class="font-mono">{{ $teacher->employee_number }}</span></dd>

                <dt class="text-gray-500">{{ __('Status') }}</dt>
                <dd class="sm:col-span-2"><x-badge :variant="$teacher->status->badgeVariant()">{{ $teacher->status->label() }}</x-badge></dd>

                <dt class="text-gray-500">{{ __('Start date') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">{{ $teacher->employed_on?->toFormattedDateString() ?: '—' }}</dd>
            </dl>
        </x-card>

        {{-- Contact --}}
        <x-card :title="__('Contact')">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                <dt class="text-gray-500">{{ __('Email') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">{{ $teacher->email ?: '—' }}</dd>
                <dt class="text-gray-500">{{ __('Phone') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">{{ $teacher->phone ?: '—' }}</dd>
                <dt class="text-gray-500">{{ __('Address') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">
                    {{ collect([$teacher->address_line1, $teacher->address_line2, $teacher->city, $teacher->state])->filter()->join(', ') ?: '—' }}
                </dd>
            </dl>
            @if ($teacher->notes)
                <div class="mt-4 border-t border-gray-100 pt-3">
                    <p class="text-xs font-medium text-gray-500">{{ __('Notes') }}</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $teacher->notes }}</p>
                </div>
            @endif
        </x-card>

        {{-- Application account --}}
        @can('staff.manage')
            <x-card :title="__('Application account')">
                @if ($teacher->user)
                    <p class="text-sm text-gray-700">
                        {{ __('Linked to') }} <span class="font-medium">{{ $teacher->user->name }}</span>
                        <span class="text-gray-400">({{ $teacher->user->email }})</span>
                    </p>
                @else
                    <p class="text-sm text-gray-500">{{ __('Not linked to a login. A teacher record does not need an account.') }}</p>
                @endif

                <form method="POST" action="{{ route('teachers.user', $teacher->id) }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-1">
                        <label for="user_id" class="block text-sm font-medium text-gray-700">{{ __('Member') }}</label>
                        <select id="user_id" name="user_id"
                            class="block w-64 rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            <option value="">{{ __('— Not linked —') }}</option>
                            @foreach ($members as $member)
                                <option value="{{ $member->id }}" @selected((int) old('user_id', $teacher->user_id) === $member->id)>
                                    {{ $member->name }} ({{ $member->email }})
                                </option>
                            @endforeach
                        </select>
                        @error('user_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <x-button type="submit" variant="secondary" size="sm">{{ __('Save link') }}</x-button>
                </form>
                <p class="mt-2 text-xs text-gray-400">{{ __('Only existing members of this school can be linked. M11 does not create accounts or send invitations.') }}</p>
            </x-card>
        @endcan

        {{-- Status control --}}
        @can('staff.manage')
            <x-card :title="__('Employment status')">
                <form method="POST" action="{{ route('teachers.status', $teacher->id) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-1">
                        <label for="status" class="block text-sm font-medium text-gray-700">{{ __('Status') }}</label>
                        <select id="status" name="status"
                            class="block w-56 rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            @foreach ($statuses as $s)
                                <option value="{{ $s->value }}" @selected($teacher->status === $s)>{{ $s->label() }}</option>
                            @endforeach
                        </select>
                        @error('status') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <x-button type="submit" variant="secondary" size="sm">{{ __('Update status') }}</x-button>
                </form>
                <p class="mt-2 text-xs text-gray-400">{{ __('Resigned teachers are kept — their record and assignment history stay.') }}</p>
            </x-card>
        @endcan

        {{-- Assignments --}}
        <x-card :title="__('Teaching assignments')">
            <x-slot:actions>
                @can('staff.manage')
                    <x-button :href="route('teachers.assignments.create', $teacher->id)" size="sm">{{ __('Add assignment') }}</x-button>
                @endcan
            </x-slot:actions>

            @if ($teacher->assignments->isEmpty())
                <p class="text-sm text-gray-500">{{ __('No assignments recorded yet.') }}</p>
            @else
                @if ($active->isNotEmpty())
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Current') }}</p>
                    <ul class="mt-1 divide-y divide-gray-100">
                        @foreach ($active as $assignment)
                            @include('teachers.assignments._row', ['assignment' => $assignment])
                        @endforeach
                    </ul>
                @endif

                @if ($past->isNotEmpty())
                    <p class="mt-4 text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Past') }}</p>
                    <ul class="mt-1 divide-y divide-gray-100">
                        @foreach ($past as $assignment)
                            @include('teachers.assignments._row', ['assignment' => $assignment])
                        @endforeach
                    </ul>
                @endif
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
