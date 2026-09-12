<x-layouts.authenticated :title="__('Add question')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('cbt.questions.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Question Bank') }}</a>
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
            @include('cbt.questions._form', [
                'action' => route('cbt.questions.store'),
                'method' => null,
                'question' => null,
                'submitLabel' => __('Add question'),
            ])
        </x-card>
    </div>
</x-layouts.authenticated>
