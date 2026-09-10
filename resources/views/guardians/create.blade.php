<x-layouts.authenticated :title="__('Add guardian')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('guardians.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Guardians') }}</a>
        </p>

        <x-card :title="__('New guardian')">
            <form method="POST" action="{{ route('guardians.store') }}">
                @csrf

                @include('guardians._form')

                <div class="flex items-center gap-2 pt-6">
                    <x-button type="submit">{{ __('Add guardian') }}</x-button>
                    <x-button :href="route('guardians.index')" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
