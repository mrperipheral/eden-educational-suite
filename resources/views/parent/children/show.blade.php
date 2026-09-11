<x-layouts.authenticated :title="$student->fullName()">
    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @include('parent._child-nav', ['student' => $student, 'siblings' => $siblings, 'active' => 'profile', 'modules' => $modules])

        <x-card :title="__('Student information')">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                <dt class="text-gray-500">{{ __('Name') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">{{ $student->fullName() }}</dd>

                <dt class="text-gray-500">{{ __('Admission number') }}</dt>
                <dd class="font-mono text-gray-900 sm:col-span-2">{{ $student->admission_number }}</dd>

                <dt class="text-gray-500">{{ __('Status') }}</dt>
                <dd class="sm:col-span-2"><x-badge :variant="$student->status->badgeVariant()">{{ $student->status->label() }}</x-badge></dd>

                @if ($student->currentEnrollment)
                    <dt class="text-gray-500">{{ __('Class') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">
                        {{ $student->currentEnrollment->level?->name }}
                        @if ($student->currentEnrollment->arm) — {{ $student->currentEnrollment->arm->name }} @endif
                    </dd>

                    <dt class="text-gray-500">{{ __('Session') }}</dt>
                    <dd class="text-gray-900 sm:col-span-2">{{ $student->currentEnrollment->session?->name }}</dd>

                    @if ($student->currentEnrollment->period)
                        <dt class="text-gray-500">{{ __('Term') }}</dt>
                        <dd class="text-gray-900 sm:col-span-2">{{ $student->currentEnrollment->period->name }}</dd>
                    @endif
                @else
                    <dt class="text-gray-500">{{ __('Class') }}</dt>
                    <dd class="italic text-gray-500 sm:col-span-2">{{ __('Not currently enrolled in a class.') }}</dd>
                @endif
            </dl>
        </x-card>
    </div>
</x-layouts.authenticated>
