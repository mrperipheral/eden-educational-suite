<x-layouts.authenticated :title="__('Add a member')">
    <x-slot:actions>
        <x-button :href="route('members.index')" variant="ghost" size="sm">{{ __('Back to members') }}</x-button>
    </x-slot:actions>

    <div class="max-w-xl">
        <x-card>
            <p class="mb-4 text-sm text-gray-600">
                {{ __('Add someone who already has an account to this school. If they have not signed up yet, ask them to register first.') }}
            </p>

            <form method="POST" action="{{ route('members.store') }}" class="space-y-4">
                @csrf

                <x-input
                    name="email"
                    type="email"
                    :label="__('Account email')"
                    required
                    autofocus
                    :value="old('email')"
                />

                <div class="space-y-1">
                    <label for="role" class="block text-sm font-medium text-gray-700">{{ __('Role') }}</label>
                    <select
                        id="role"
                        name="role"
                        class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500"
                    >
                        @foreach ($assignableRoles as $role)
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    @error('role')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit">{{ __('Add member') }}</x-button>
                    <x-button :href="route('members.index')" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
