@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $available = $guardians->reject(fn ($g) => in_array($g->id, $linkedGuardianIds, true));
@endphp

<x-layouts.authenticated :title="__('Link a guardian')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('students.show', $student->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← Back to :name', ['name' => $student->shortName()]) }}</a>
        </p>

        <x-card :title="__('Link a guardian to :name', ['name' => $student->shortName()])">
            @if ($available->isEmpty())
                <x-empty-state
                    :title="$guardians->isEmpty() ? __('No guardians yet') : __('All guardians already linked')"
                    :description="$guardians->isEmpty()
                        ? __('Create a guardian record first, then link them here.')
                        : __('Every guardian on record is already linked to this student.')"
                >
                    @can('guardian.manage')
                        <x-slot:actions>
                            <x-button :href="route('guardians.create')" size="sm">{{ __('Add guardian') }}</x-button>
                        </x-slot:actions>
                    @endcan
                </x-empty-state>
            @else
                <form method="POST" action="{{ route('guardians.links.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $student->id }}">

                    <div class="space-y-1">
                        <label for="guardian_id" class="block text-sm font-medium text-gray-700">{{ __('Guardian') }} <span class="text-red-500">*</span></label>
                        <select id="guardian_id" name="guardian_id" class="{{ $selectClass }}">
                            <option value="">{{ __('Select a guardian') }}</option>
                            @foreach ($available as $guardian)
                                <option value="{{ $guardian->id }}" @selected((int) old('guardian_id') === $guardian->id)>
                                    {{ $guardian->fullName() }}@if ($guardian->phone) — {{ $guardian->phone }}@endif
                                </option>
                            @endforeach
                        </select>
                        @error('guardian_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        @error('student_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        <p class="text-xs text-gray-500">
                            {{ __('Not on the list?') }}
                            <a href="{{ route('guardians.create') }}" class="text-brand-600 hover:text-brand-700">{{ __('Add a new guardian') }}</a>
                        </p>
                    </div>

                    @include('guardians._relationship-fields')

                    <div class="flex items-center gap-2 pt-2">
                        <x-button type="submit">{{ __('Link guardian') }}</x-button>
                        <x-button :href="route('students.show', $student->id)" variant="ghost">{{ __('Cancel') }}</x-button>
                    </div>
                </form>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
