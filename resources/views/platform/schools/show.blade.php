<x-layouts.authenticated :title="$school->name">
    <x-slot:actions>
        <x-button :href="route('admin.schools.index')" variant="ghost" size="sm">{{ __('All schools') }}</x-button>
    </x-slot:actions>

    <div class="max-w-2xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card :title="__('School')">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                <dt class="text-gray-500">{{ __('Name') }}</dt>
                <dd class="sm:col-span-2 text-gray-900">{{ $school->name }}</dd>

                <dt class="text-gray-500">{{ __('Slug') }}</dt>
                <dd class="sm:col-span-2 text-gray-900">{{ $school->slug }}</dd>

                <dt class="text-gray-500">{{ __('Status') }}</dt>
                <dd class="sm:col-span-2">
                    <x-badge :variant="$school->isActive() ? 'success' : 'warning'">{{ $school->status->label() }}</x-badge>
                </dd>

                <dt class="text-gray-500">{{ __('Members') }}</dt>
                <dd class="sm:col-span-2 text-gray-900">{{ $school->users_count }}</dd>

                <dt class="text-gray-500">{{ __('School Admin assigned') }}</dt>
                <dd class="sm:col-span-2">
                    <x-badge :variant="$hasSchoolAdmin ? 'success' : 'gray'">
                        {{ $hasSchoolAdmin ? __('Yes') : __('Not yet') }}
                    </x-badge>
                </dd>

                <dt class="text-gray-500">{{ __('Created') }}</dt>
                <dd class="sm:col-span-2 text-gray-900">{{ $school->created_at->toFormattedDateString() }}</dd>
            </dl>
        </x-card>

        <x-card :title="__('Manage this school')">
            <p class="mb-4 text-sm text-gray-600">
                {{ __('School settings, members and academic sessions are managed inside the school. Enter it to continue onboarding.') }}
            </p>
            @if ($school->isActive())
                <form method="POST" action="{{ route('school-context.store') }}">
                    @csrf
                    <input type="hidden" name="school" value="{{ $school->id }}">
                    <x-button type="submit">{{ __('Enter school') }}</x-button>
                </form>
            @else
                <p class="text-sm text-gray-500">{{ __('This school is suspended and cannot be entered.') }}</p>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
