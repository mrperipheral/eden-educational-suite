<x-layouts.authenticated :title="__('Create examination')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('cbt.examinations.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Examinations') }}</a>
        </p>

        @if ($errors->any())
            <x-alert variant="danger" :title="__('Please fix the following')">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        @if (! $unrestricted && $assignments->isEmpty())
            <x-empty-state
                :title="__('No teaching assignments found')"
                :description="__('You are not currently assigned to teach any class/subject, so there is nothing to create an examination for.')"
            />
        @else
            <x-card>
                @include('cbt.examinations._form', [
                    'action' => route('cbt.examinations.store'),
                    'method' => null,
                    'examination' => null,
                    'submitLabel' => __('Create examination'),
                ])
            </x-card>
        @endif
    </div>
</x-layouts.authenticated>
