<x-layouts.authenticated :title="$guardian->fullName()">
    @can('guardian.manage')
        <x-slot:actions>
            <x-button :href="route('guardians.edit', $guardian->id)" size="sm" variant="secondary">{{ __('Edit details') }}</x-button>
        </x-slot:actions>
    @endcan

    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <p class="text-sm">
            <a href="{{ route('guardians.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Guardians') }}</a>
        </p>

        {{-- Identity & contact --}}
        <x-card :title="__('Guardian')">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                <dt class="text-gray-500">{{ __('Name') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">
                    {{ $guardian->fullName() }}
                    @if ($guardian->preferred_name)
                        <span class="text-gray-400">({{ $guardian->preferred_name }})</span>
                    @endif
                </dd>

                <dt class="text-gray-500">{{ __('Phone') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">{{ $guardian->phone ?: '—' }}</dd>

                <dt class="text-gray-500">{{ __('Alternate phone') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">{{ $guardian->alt_phone ?: '—' }}</dd>

                <dt class="text-gray-500">{{ __('Email') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">{{ $guardian->email ?: '—' }}</dd>

                <dt class="text-gray-500">{{ __('Address') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">
                    {{ collect([$guardian->address_line1, $guardian->address_line2, $guardian->city, $guardian->state])->filter()->join(', ') ?: '—' }}
                </dd>
            </dl>
            @if ($guardian->notes)
                <div class="mt-4 border-t border-gray-100 pt-3">
                    <p class="text-xs font-medium text-gray-500">{{ __('Notes') }}</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $guardian->notes }}</p>
                </div>
            @endif
        </x-card>

        {{-- Application account --}}
        @can('guardian.manage')
            <x-card :title="__('Application account')">
                @if ($guardian->user)
                    <p class="text-sm text-gray-700">
                        {{ __('Linked to') }} <span class="font-medium">{{ $guardian->user->name }}</span>
                        <span class="text-gray-400">({{ $guardian->user->email }})</span>
                    </p>
                @else
                    <p class="text-sm text-gray-500">{{ __('Not linked to a login. Link an existing member so this guardian can sign in to the Parent Portal.') }}</p>
                @endif

                <form method="POST" action="{{ route('guardians.user', $guardian->id) }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-1">
                        <label for="user_id" class="block text-sm font-medium text-gray-700">{{ __('Member') }}</label>
                        <select id="user_id" name="user_id"
                            class="block w-64 rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            <option value="">{{ __('— Not linked —') }}</option>
                            @foreach ($members as $member)
                                <option value="{{ $member->id }}" @selected((int) old('user_id', $guardian->user_id) === $member->id)>
                                    {{ $member->name }} ({{ $member->email }})
                                </option>
                            @endforeach
                        </select>
                        @error('user_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <x-button type="submit" variant="secondary" size="sm">{{ __('Save link') }}</x-button>
                </form>
                <p class="mt-2 text-xs text-gray-400">{{ __('Only existing members of this school can be linked. M16 does not create accounts or send invitations — the member also needs the Parent role to use the portal.') }}</p>
            </x-card>
        @endcan

        {{-- Linked students --}}
        <x-card :title="__('Linked students')">
            @if ($guardian->studentLinks->isEmpty())
                <p class="text-sm text-gray-500">
                    {{ __('No students linked yet. Open a student\'s profile and use "Add guardian" to link them here.') }}
                </p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($guardian->studentLinks->sortBy(fn ($l) => $l->student->last_name.$l->student->first_name) as $link)
                        <li x-data="{ editing: false }" class="py-3 first:pt-0 last:pb-0">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900">
                                        <a href="{{ route('students.show', $link->student->id) }}" class="hover:text-brand-700">
                                            {{ $link->student->displayName() }} {{ $link->student->last_name }}
                                        </a>
                                        @if ($link->is_primary)
                                            <x-badge variant="brand" class="ml-1">{{ __('Primary') }}</x-badge>
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <span class="font-mono">{{ $link->student->admission_number }}</span>
                                        · {{ $link->relationship->label() }}
                                        @if ($link->student->currentEnrollment)
                                            · {{ $link->student->currentEnrollment->level?->name }}{{ $link->student->currentEnrollment->arm ? ' — '.$link->student->currentEnrollment->arm->name : '' }}
                                        @endif
                                    </p>
                                </div>
                                @can('guardian.manage')
                                    <div class="flex shrink-0 items-center gap-2">
                                        <x-button type="button" size="sm" variant="ghost" x-on:click="editing = ! editing">{{ __('Edit') }}</x-button>
                                        <x-confirm :action="route('guardians.links.destroy', $link->id)" method="DELETE" size="sm"
                                            :confirm="__('Unlink')"
                                            :title="__('Unlink guardian?')"
                                            :message="__('This removes the relationship. Neither the guardian nor the student record is deleted.')">
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
    </div>
</x-layouts.authenticated>
