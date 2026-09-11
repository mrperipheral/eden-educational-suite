<x-layouts.authenticated :title="__('Edit fee structure')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('fees.structures.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Fee structures') }}</a>
        </p>

        <x-card :title="__('Edit fee structure')">
            <p class="mb-4 text-xs text-gray-500">
                {{ __('Changing the amount only affects charges raised from this structure in the future — charges already raised keep their original amount.') }}
            </p>

            <form method="POST" action="{{ route('fees.structures.update', $structure) }}">
                @csrf
                @method('PATCH')

                @include('fees.structures._form')

                <div class="flex items-center gap-2 pt-6">
                    <x-button type="submit">{{ __('Save changes') }}</x-button>
                    <x-button :href="route('fees.structures.index')" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
