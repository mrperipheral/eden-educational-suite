<x-layouts.authenticated :title="__('Create school')">
    <div class="max-w-xl">
        <x-card>
            <form method="POST" action="{{ route('admin.schools.store') }}" class="space-y-4">
                @csrf

                <x-input
                    name="name"
                    :label="__('School name')"
                    required
                    autofocus
                    :value="old('name')"
                />

                <x-input
                    name="slug"
                    :label="__('Slug')"
                    :value="old('slug')"
                    :hint="__('Optional. Lowercase letters, numbers and hyphens. Generated from the name if left blank.')"
                    placeholder="alpha-academy"
                />

                <x-input
                    name="initial_admin_email"
                    type="email"
                    :label="__('Initial School Admin (optional)')"
                    :value="old('initial_admin_email')"
                    :hint="__('Email of an existing, active account. They will be added to the school as School Admin.')"
                />

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit">{{ __('Create school') }}</x-button>
                    <x-button :href="route('admin.schools.index')" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
