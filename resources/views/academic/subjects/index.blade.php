<x-layouts.authenticated :title="__('Subjects')">
    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @include('academic._nav')

        @can('academics.manage')
            <x-card :title="__('Add a subject')">
                <form method="POST" action="{{ route('academic.subjects.store') }}" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                        <div class="sm:col-span-3">
                            <x-input name="name" :label="__('Name')" :value="old('name')" required placeholder="Mathematics" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input name="code" :label="__('Code')" :value="old('code')" required placeholder="MTH" />
                        </div>
                        <x-input name="position" type="number" min="0" :label="__('Order')" :value="old('position', 0)" />
                    </div>

                    <x-input name="description" :label="__('Description')" :value="old('description')" :hint="__('Optional.')" />

                    <div class="pt-1">
                        <x-button type="submit">{{ __('Add subject') }}</x-button>
                    </div>
                </form>
            </x-card>
        @endcan

        <form method="GET" action="{{ route('academic.subjects.index') }}" class="flex gap-2">
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Search name or code…') }}"
                class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
            <x-button type="submit" variant="secondary">{{ __('Search') }}</x-button>
        </form>

        @if ($subjects->isEmpty())
            <x-empty-state
                :title="$search !== '' ? __('No subjects match ":q"', ['q' => $search]) : __('No subjects yet')"
                :description="$search !== '' ? __('Try a different search.') : __('Build your school\'s subject list — nothing is preset.')"
            />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($subjects as $subject)
                        <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    {{ $subject->name }}
                                    <span class="ml-1 font-mono text-xs text-gray-400">{{ $subject->code }}</span>
                                    @unless ($subject->is_active)
                                        <x-badge variant="warning" class="ml-1">{{ __('Inactive') }}</x-badge>
                                    @endunless
                                </p>
                                @if ($subject->description)
                                    <p class="truncate text-xs text-gray-500">{{ $subject->description }}</p>
                                @endif
                            </div>
                            @can('academics.manage')
                                <x-button :href="route('academic.subjects.edit', $subject->id)" size="sm" variant="ghost">{{ __('Edit') }}</x-button>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $subjects->links() }}
        @endif
    </div>
</x-layouts.authenticated>
