<x-layouts.authenticated :title="__('Levels & classes')">
    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @include('academic._nav')

        @can('academics.manage')
            <x-card :title="__('Add a level')">
                <form method="POST" action="{{ route('academic.levels.store') }}" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                        <div class="sm:col-span-3">
                            <x-input name="name" :label="__('Name')" :value="old('name')" required placeholder="Primary 1" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input name="code" :label="__('Short code')" :value="old('code')" required placeholder="PRI1" />
                        </div>
                        <x-input name="position" type="number" min="1" :label="__('Order')" :value="old('position')" required />
                    </div>

                    <div class="pt-1">
                        <x-button type="submit">{{ __('Add level') }}</x-button>
                    </div>
                </form>
            </x-card>
        @endcan

        @if ($levels->isEmpty())
            <x-empty-state
                :title="__('No levels yet')"
                :description="__('Define your school\'s classes / year groups. Any names and order — nothing is preset.')"
            />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($levels as $level)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <span class="text-gray-400">{{ $level->position }}.</span>
                                    {{ $level->name }}
                                    <span class="ml-1 font-mono text-xs text-gray-400">{{ $level->code }}</span>
                                    @unless ($level->is_active)
                                        <x-badge variant="warning" class="ml-1">{{ __('Inactive') }}</x-badge>
                                    @endunless
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ trans_choice('{0}no arms|{1}:count arm|[2,*]:count arms', $level->arms_count, ['count' => $level->arms_count]) }}
                                    · {{ trans_choice('{0}no subjects|{1}:count subject|[2,*]:count subjects', $level->subjects_count, ['count' => $level->subjects_count]) }}
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <x-button :href="route('academic.levels.show', $level->id)" size="sm" variant="secondary">
                                    {{ __('Arms & subjects') }}
                                </x-button>
                                @can('academics.manage')
                                    <x-button :href="route('academic.levels.edit', $level->id)" size="sm" variant="ghost">
                                        {{ __('Edit') }}
                                    </x-button>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $levels->links() }}
        @endif
    </div>
</x-layouts.authenticated>
