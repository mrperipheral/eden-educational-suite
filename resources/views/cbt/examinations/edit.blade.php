<x-layouts.authenticated :title="__('Edit examination')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('cbt.examinations.show', $examination->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← :title', ['title' => $examination->title]) }}</a>
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
            @include('cbt.examinations._form', [
                'action' => route('cbt.examinations.update', $examination->id),
                'method' => 'PATCH',
                'examination' => $examination,
                'unrestricted' => true,
                'submitLabel' => __('Save changes'),
            ])
        </x-card>
    </div>
</x-layouts.authenticated>
