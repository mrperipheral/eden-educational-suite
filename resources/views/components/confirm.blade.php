@props([
    'action',
    'method' => 'DELETE',
    'title' => 'Are you sure?',
    'message' => 'This action cannot be undone.',
    'confirm' => 'Confirm',
    'variant' => 'danger',
])

{{--
    Inline "confirm then submit" pattern. Renders a trigger button that opens a
    small confirmation dialog before POSTing a hidden form. The confirmation is
    a UX guard only -- the destructive route must still enforce authorization
    server-side.

    Usage:
        <x-confirm :action="route('things.destroy', $thing)" confirm="Delete">
            Delete
        </x-confirm>
--}}
<div x-data="{ open: false }" class="inline-block">
    <x-button type="button" :variant="$variant" x-on:click="open = true" {{ $attributes }}>
        {{ $slot }}
    </x-button>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-on:keydown.escape.window="open = false"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
        >
            <div x-show="open" x-transition.opacity x-on:click="open = false" class="absolute inset-0 bg-gray-900/50"></div>

            <div x-show="open" x-transition class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ $message }}</p>

                <div class="mt-6 flex justify-end gap-2">
                    <x-button type="button" variant="secondary" x-on:click="open = false">Cancel</x-button>
                    <form method="POST" action="{{ $action }}">
                        @csrf
                        @method($method)
                        <x-button type="submit" :variant="$variant">{{ $confirm }}</x-button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
