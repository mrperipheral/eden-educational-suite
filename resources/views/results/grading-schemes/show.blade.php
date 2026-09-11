@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp

<x-layouts.authenticated :title="$scheme->name">
    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        <p class="text-sm">
            <a href="{{ route('results.grading-schemes.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Grading schemes') }}</a>
        </p>

        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-gray-900">
                        {{ $scheme->name }}
                        @unless ($scheme->is_active) <x-badge variant="gray">{{ __('Inactive') }}</x-badge> @endunless
                    </p>
                    @if ($scheme->description) <p class="text-xs text-gray-500">{{ $scheme->description }}</p> @endif
                </div>
            </div>

            @can('result.manage')
                <form method="PATCH" action="{{ route('results.grading-schemes.update', $scheme->id) }}" class="mt-4 space-y-2 border-t border-gray-100 pt-4" x-data>
                    @csrf
                    @method('PATCH')
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <x-input name="name" :label="__('Name')" :value="$scheme->name" required />
                        <x-input name="description" :label="__('Description')" :value="$scheme->description" />
                    </div>
                    <x-checkbox name="is_active" :label="__('Active')" :checked="$scheme->is_active" />
                    <x-button type="submit" size="sm">{{ __('Save') }}</x-button>
                </form>
            @endcan
        </x-card>

        <x-card :title="__('Grade bands')">
            @if ($scheme->grades->isEmpty())
                <x-empty-state :title="__('No grade bands yet')" :description="__('Add the first band below — bands must not overlap.')" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-gray-500">
                                <th class="py-2 pr-4">{{ __('Code') }}</th>
                                <th class="py-2 pr-4">{{ __('Range') }}</th>
                                <th class="py-2 pr-4">{{ __('Remark') }}</th>
                                <th class="py-2 pr-4">{{ __('Status') }}</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($scheme->grades as $grade)
                                <tr x-data="{ editing: false }">
                                    <td class="py-2 pr-4 font-medium text-gray-900" x-show="!editing">{{ $grade->code }}</td>
                                    <td class="py-2 pr-4" x-show="!editing">{{ $fmt($grade->min_percentage) }}–{{ $fmt($grade->max_percentage) }}%</td>
                                    <td class="py-2 pr-4 text-gray-500" x-show="!editing">{{ $grade->remark }}</td>
                                    <td class="py-2 pr-4" x-show="!editing">
                                        <x-badge :variant="$grade->is_active ? 'success' : 'gray'">{{ $grade->is_active ? __('Active') : __('Inactive') }}</x-badge>
                                    </td>
                                    <td class="py-2" x-show="!editing">
                                        @can('result.manage')
                                            <div class="flex gap-2">
                                                <x-button type="button" size="sm" variant="ghost" x-on:click="editing = true">{{ __('Edit') }}</x-button>
                                                <x-confirm :action="route('results.grading-schemes.grades.destroy', $grade->id)" size="sm" variant="ghost"
                                                    :confirm="__('Remove')" :title="__('Remove this grade band?')">
                                                    {{ __('Remove') }}
                                                </x-confirm>
                                            </div>
                                        @endcan
                                    </td>
                                    @can('result.manage')
                                        <td colspan="5" x-show="editing" x-cloak class="py-2">
                                            <form method="POST" action="{{ route('results.grading-schemes.grades.update', $grade->id) }}" class="grid grid-cols-2 gap-2 sm:grid-cols-6">
                                                @csrf
                                                @method('PATCH')
                                                <x-input name="code" :value="$grade->code" required />
                                                <x-input name="min_percentage" type="number" step="0.01" min="0" max="100" :value="$fmt($grade->min_percentage)" required />
                                                <x-input name="max_percentage" type="number" step="0.01" min="0" max="100" :value="$fmt($grade->max_percentage)" required />
                                                <x-input name="remark" :value="$grade->remark" class="sm:col-span-2" />
                                                <div class="flex items-center gap-2">
                                                    <x-button type="submit" size="sm">{{ __('Save') }}</x-button>
                                                    <x-button type="button" size="sm" variant="ghost" x-on:click="editing = false">{{ __('Cancel') }}</x-button>
                                                </div>
                                            </form>
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        @can('result.manage')
            <x-card :title="__('Add a grade band')">
                <form method="POST" action="{{ route('results.grading-schemes.grades.store', $scheme->id) }}" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    @csrf
                    <x-input name="code" :label="__('Code')" :value="old('code')" placeholder="A" required />
                    <x-input name="min_percentage" type="number" step="0.01" min="0" max="100" :label="__('Min %')" :value="old('min_percentage')" required />
                    <x-input name="max_percentage" type="number" step="0.01" min="0" max="100" :label="__('Max %')" :value="old('max_percentage')" required />
                    <x-input name="remark" :label="__('Remark (optional)')" :value="old('remark')" placeholder="Excellent" />
                    <div class="sm:col-span-4">
                        <x-button type="submit">{{ __('Add grade band') }}</x-button>
                    </div>
                </form>
            </x-card>
        @endcan
    </div>
</x-layouts.authenticated>
