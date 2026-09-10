<x-layouts.authenticated :title="__(':name — arms & subjects', ['name' => $level->name])">
    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @include('academic._nav')

        <x-card :title="__('Level')">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
                <dt class="text-gray-500">{{ __('Name') }}</dt>
                <dd class="text-gray-900 sm:col-span-2">
                    {{ $level->name }}
                    <span class="ml-1 font-mono text-xs text-gray-400">{{ $level->code }}</span>
                    @unless ($level->is_active)
                        <x-badge variant="warning" class="ml-1">{{ __('Inactive') }}</x-badge>
                    @endunless
                </dd>
            </dl>
        </x-card>

        {{-- Arms --}}
        @can('academics.manage')
            <x-card :title="__('Add an arm / stream')">
                <form method="POST" action="{{ route('academic.arms.store', $level->id) }}" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                        <div class="sm:col-span-3">
                            <x-input name="name" :label="__('Name')" :value="old('name')" required placeholder="Gold" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input name="code" :label="__('Code')" :value="old('code')" required placeholder="G" />
                        </div>
                        <x-input name="position" type="number" min="1" :label="__('Order')" :value="old('position')" required />
                    </div>

                    <div class="pt-1">
                        <x-button type="submit">{{ __('Add arm') }}</x-button>
                    </div>
                </form>
            </x-card>
        @endcan

        @if ($level->arms->isEmpty())
            <x-empty-state :title="__('No arms')" :description="__('Arms are optional — add them only if this level splits into streams.')" />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($level->arms as $arm)
                        <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
                            <p class="truncate text-sm font-medium text-gray-900">
                                <span class="text-gray-400">{{ $arm->position }}.</span>
                                {{ $arm->name }}
                                <span class="ml-1 font-mono text-xs text-gray-400">{{ $arm->code }}</span>
                                @unless ($arm->is_active)
                                    <x-badge variant="warning" class="ml-1">{{ __('Inactive') }}</x-badge>
                                @endunless
                            </p>
                            @can('academics.manage')
                                <x-button :href="route('academic.arms.edit', $arm->id)" size="sm" variant="ghost">{{ __('Edit') }}</x-button>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        {{-- Subjects offered --}}
        <x-card :title="__('Subjects offered')">
            @if ($subjects->isEmpty())
                <p class="text-sm text-gray-500">
                    {{ __('No subjects defined yet.') }}
                    <a href="{{ route('academic.subjects.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('Add subjects') }}</a>
                </p>
            @else
                @can('academics.manage')
                    <form method="POST" action="{{ route('academic.levels.subjects', $level->id) }}" class="space-y-3">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                            @foreach ($subjects as $subject)
                                <label class="flex items-start gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="subjects[]" value="{{ $subject->id }}"
                                        @checked(in_array($subject->id, $offeredSubjectIds, true))
                                        class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                    <span>
                                        {{ $subject->name }}
                                        @unless ($subject->is_active)
                                            <span class="text-xs text-gray-400">({{ __('inactive') }})</span>
                                        @endunless
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('subjects.*') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                        <div class="pt-1">
                            <x-button type="submit" size="sm">{{ __('Save subjects') }}</x-button>
                        </div>
                    </form>
                @else
                    @php($offered = $subjects->whereIn('id', $offeredSubjectIds))
                    @if ($offered->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('No subjects assigned to this level.') }}</p>
                    @else
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($offered as $subject)
                                <li><x-badge>{{ $subject->name }}</x-badge></li>
                            @endforeach
                        </ul>
                    @endif
                @endcan
            @endif
        </x-card>

        <p class="text-sm">
            <a href="{{ route('academic.levels.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← All levels') }}</a>
        </p>
    </div>
</x-layouts.authenticated>
