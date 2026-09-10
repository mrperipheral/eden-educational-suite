<x-layouts.authenticated :title="__('Modules')">
    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @include('settings.school._nav')

        @error('module')
            <x-alert variant="danger">{{ $message }}</x-alert>
        @enderror

        <x-card :title="__('Modules & features')">
            <p class="text-sm text-gray-600">
                {{ __('Turn parts of the app on or off for your school. Modules marked') }}
                <x-badge variant="warning">{{ __('Planned') }}</x-badge>
                {{ __('are not built yet — your choice is saved and applied when they launch.') }}
            </p>
            @cannot('school.settings.update')
                <p class="mt-3 text-xs text-gray-400">{{ __('You have read-only access to school settings.') }}</p>
            @endcannot
        </x-card>

        @foreach ($groups as $groupLabel => $rows)
            <x-card :title="$groupLabel" :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($rows as $row)
                        @php($module = $row['module'])
                        <li class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-medium text-gray-900">{{ $module->label() }}</p>

                                    @if ($row['available'])
                                        <x-badge variant="success">{{ __('Available') }}</x-badge>
                                    @else
                                        <x-badge variant="warning">{{ __('Planned') }}</x-badge>
                                    @endif

                                    @if ($row['enabled'])
                                        <x-badge variant="brand">{{ __('Enabled') }}</x-badge>
                                    @else
                                        <x-badge>{{ __('Disabled') }}</x-badge>
                                    @endif
                                </div>

                                <p class="mt-1 text-xs text-gray-500">{{ $module->description() }}</p>

                                @if ($row['dependencies'])
                                    <p class="mt-1 text-xs text-gray-400">
                                        {{ __('Requires') }}:
                                        {{ collect($row['dependencies'])->map(fn ($dependency) => $dependency->label())->join(', ') }}
                                    </p>
                                @endif
                            </div>

                            @can('school.settings.update')
                                <form
                                    method="POST"
                                    action="{{ route('settings.school.modules.update', $module->value) }}"
                                    class="shrink-0"
                                >
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="enabled" value="{{ $row['enabled'] ? 0 : 1 }}">
                                    <x-button
                                        type="submit"
                                        size="sm"
                                        :variant="$row['enabled'] ? 'ghost' : 'secondary'"
                                    >
                                        {{ $row['enabled'] ? __('Disable') : __('Enable') }}
                                    </x-button>
                                </form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endforeach
    </div>
</x-layouts.authenticated>
