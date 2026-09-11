<x-layouts.authenticated :title="$student->fullName()">
    @can('student.manage')
        <x-slot:actions>
            <x-button :href="route('students.edit', $student->id)" size="sm" variant="secondary">{{ __('Edit details') }}</x-button>
        </x-slot:actions>
    @endcan

    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <p class="text-sm">
            <a href="{{ route('students.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Students') }}</a>
        </p>

        {{-- Identity --}}
        <x-card :title="__('Student')">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                <dt class="text-gray-500">{{ __('Name') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">
                    {{ $student->fullName() }}
                    @if ($student->preferred_name)
                        <span class="text-gray-400">({{ $student->preferred_name }})</span>
                    @endif
                </dd>

                <dt class="text-gray-500">{{ __('Admission number') }}</dt>
                <dd class="text-gray-900 sm:col-span-2"><span class="font-mono">{{ $student->admission_number }}</span></dd>

                <dt class="text-gray-500">{{ __('Status') }}</dt>
                <dd class="sm:col-span-2"><x-badge :variant="$student->status->badgeVariant()">{{ $student->status->label() }}</x-badge></dd>

                <dt class="text-gray-500">{{ __('Date of birth') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">{{ $student->date_of_birth?->toFormattedDateString() ?: '—' }}</dd>

                <dt class="text-gray-500">{{ __('Gender') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">{{ $student->gender?->label() ?: '—' }}</dd>

                <dt class="text-gray-500">{{ __('Admitted') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">{{ $student->admitted_on?->toFormattedDateString() ?: '—' }}</dd>
            </dl>
        </x-card>

        {{-- Contact --}}
        <x-card :title="__('Contact')">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                <dt class="text-gray-500">{{ __('Email') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">{{ $student->contact_email ?: '—' }}</dd>
                <dt class="text-gray-500">{{ __('Phone') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">{{ $student->contact_phone ?: '—' }}</dd>
                <dt class="text-gray-500">{{ __('Address') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">
                    {{ collect([$student->address_line1, $student->address_line2, $student->city, $student->state])->filter()->join(', ') ?: '—' }}
                </dd>
            </dl>
            @if ($student->notes)
                <div class="mt-4 border-t border-gray-100 pt-3">
                    <p class="text-xs font-medium text-gray-500">{{ __('Notes') }}</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $student->notes }}</p>
                </div>
            @endif
        </x-card>

        {{-- Application account --}}
        @can('student.manage')
            <x-card :title="__('Application account')">
                @if ($student->user)
                    <p class="text-sm text-gray-700">
                        {{ __('Linked to') }} <span class="font-medium">{{ $student->user->name }}</span>
                        <span class="text-gray-400">({{ $student->user->email }})</span>
                    </p>
                @else
                    <p class="text-sm text-gray-500">{{ __('Not linked to a login. Link an existing member so this student can sign in to the Student Portal.') }}</p>
                @endif

                <form method="POST" action="{{ route('students.user', $student->id) }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-1">
                        <label for="user_id" class="block text-sm font-medium text-gray-700">{{ __('Member') }}</label>
                        <select id="user_id" name="user_id"
                            class="block w-64 rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            <option value="">{{ __('— Not linked —') }}</option>
                            @foreach ($members as $member)
                                <option value="{{ $member->id }}" @selected((int) old('user_id', $student->user_id) === $member->id)>
                                    {{ $member->name }} ({{ $member->email }})
                                </option>
                            @endforeach
                        </select>
                        @error('user_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <x-button type="submit" variant="secondary" size="sm">{{ __('Save link') }}</x-button>
                </form>
                <p class="mt-2 text-xs text-gray-400">{{ __('Only existing members of this school can be linked. M17 does not create accounts or send invitations — the member also needs the Student role to use the portal.') }}</p>
            </x-card>
        @endcan

        {{-- Guardians --}}
        @module('guardians')
            @can('guardian.view')
                <x-card :title="__('Parents / guardians')">
                    <x-slot:actions>
                        @can('guardian.manage')
                            <x-button :href="route('guardians.links.create', $student->id)" size="sm">{{ __('Add guardian') }}</x-button>
                        @endcan
                    </x-slot:actions>

                    @if ($student->guardianLinks->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('No parents or guardians linked yet.') }}</p>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($student->guardianLinks->sortByDesc('is_primary') as $link)
                                <li x-data="{ editing: false }" class="py-3 first:pt-0 last:pb-0">
                                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-900">
                                                <a href="{{ route('guardians.show', $link->guardian->id) }}" class="hover:text-brand-700">
                                                    {{ $link->guardian->fullName() }}
                                                </a>
                                                @if ($link->is_primary)
                                                    <x-badge variant="brand" class="ml-1">{{ __('Primary') }}</x-badge>
                                                @endif
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                {{ $link->relationship->label() }}
                                                @if ($link->guardian->phone) · {{ $link->guardian->phone }} @endif
                                                @if ($link->guardian->email) · {{ $link->guardian->email }} @endif
                                            </p>
                                        </div>
                                        @can('guardian.manage')
                                            <div class="flex shrink-0 items-center gap-2">
                                                <x-button type="button" size="sm" variant="ghost" x-on:click="editing = ! editing">{{ __('Edit') }}</x-button>
                                                <x-confirm :action="route('guardians.links.destroy', $link->id)" method="DELETE" size="sm"
                                                    :confirm="__('Unlink')"
                                                    :title="__('Unlink guardian?')"
                                                    :message="__('This removes the relationship. The guardian record is kept.')">
                                                    {{ __('Unlink') }}
                                                </x-confirm>
                                            </div>
                                        @endcan
                                    </div>

                                    @can('guardian.manage')
                                        <form x-show="editing" x-cloak method="POST" action="{{ route('guardians.links.update', $link->id) }}"
                                            class="mt-3 rounded-md bg-gray-50 p-3">
                                            @csrf
                                            @method('PATCH')
                                            @include('guardians._relationship-fields', ['link' => $link])
                                            <div class="mt-3 flex items-center gap-2">
                                                <x-button type="submit" size="sm">{{ __('Save') }}</x-button>
                                                <x-button type="button" size="sm" variant="ghost" x-on:click="editing = false">{{ __('Cancel') }}</x-button>
                                            </div>
                                        </form>
                                    @endcan
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endcan
        @endmodule

        {{-- Status control --}}
        @can('student.manage')
            <x-card :title="__('Lifecycle status')">
                <form method="POST" action="{{ route('students.status', $student->id) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-1">
                        <label for="status" class="block text-sm font-medium text-gray-700">{{ __('Status') }}</label>
                        <select id="status" name="status"
                            class="block w-56 rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            @foreach ($statuses as $s)
                                <option value="{{ $s->value }}" @selected($student->status === $s)>{{ $s->label() }}</option>
                            @endforeach
                        </select>
                        @error('status') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <x-button type="submit" variant="secondary" size="sm">{{ __('Update status') }}</x-button>
                </form>
                <p class="mt-2 text-xs text-gray-400">{{ __('Withdrawn / graduated students are kept — their record and history stay.') }}</p>
            </x-card>
        @endcan

        {{-- Enrollment history --}}
        <x-card :title="__('Academic placement')">
            <x-slot:actions>
                @can('student.manage')
                    <x-button :href="route('students.enrollments.create', $student->id)" size="sm">{{ __('Add enrollment') }}</x-button>
                @endcan
            </x-slot:actions>

            @if ($student->enrollments->isEmpty())
                <p class="text-sm text-gray-500">{{ __('No enrollments recorded. Add one to place this student in a class.') }}</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($student->enrollments as $enrollment)
                        <li class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900">
                                    {{ $enrollment->level?->name }}{{ $enrollment->arm ? ' — '.$enrollment->arm->name : '' }}
                                    <x-badge :variant="$enrollment->status->badgeVariant()" class="ml-1">{{ $enrollment->status->label() }}</x-badge>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $enrollment->session?->name }}{{ $enrollment->period ? ' · '.$enrollment->period->name : '' }}
                                    · {{ $enrollment->started_on->toFormattedDateString() }}@if ($enrollment->ended_on) – {{ $enrollment->ended_on->toFormattedDateString() }} @endif
                                </p>
                            </div>
                            @can('student.manage')
                                <div class="shrink-0">
                                    <x-button :href="route('students.enrollments.edit', $enrollment->id)" size="sm" variant="ghost">{{ __('Edit') }}</x-button>
                                </div>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
