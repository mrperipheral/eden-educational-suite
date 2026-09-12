<x-layouts.authenticated :title="__('Record an assessment')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('entry-assessments.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Entry / Placement Assessment') }}</a>
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

        <x-card>
            @include('entry-assessments._form', [
                'action' => route('entry-assessments.store'),
                'method' => null,
                'assessment' => null,
                'submitLabel' => __('Record assessment'),
            ])
        </x-card>
    </div>
</x-layouts.authenticated>
