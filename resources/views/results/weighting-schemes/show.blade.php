@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $total = $scheme->totalWeight();
    $complete = abs($total - 100.0) < 0.001;
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
            <a href="{{ route('results.weighting-schemes.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Weighting schemes') }}</a>
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
                <x-badge :variant="$complete ? 'success' : 'warning'">{{ __('Total: :n%', ['n' => $fmt($total)]) }}</x-badge>
            </div>
            @unless ($complete)
                <p class="mt-2 text-xs text-amber-600">{{ __('Weights must total exactly 100% before this scheme can be selected for a result run.') }}</p>
            @endunless

            @can('result.manage')
                <form method="PATCH" action="{{ route('results.weighting-schemes.update', $scheme->id) }}" class="mt-4 space-y-2 border-t border-gray-100 pt-4">
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

        <x-card :title="__('Category weights')">
            @if ($scheme->items->isEmpty())
                <x-empty-state :title="__('No categories weighted yet')" :description="__('Add the categories below — their weights must sum to 100%.')" />
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($scheme->items as $item)
                        <li class="flex items-center justify-between gap-3 py-2.5" x-data="{ editing: false }">
                            <div x-show="!editing" class="flex items-center gap-2 text-sm">
                                <span class="font-medium text-gray-900">{{ $item->category?->name }}</span>
                                <span class="text-gray-500">{{ $fmt($item->weight_percentage) }}%</span>
                            </div>
                            @can('result.manage')
                                <div x-show="!editing" class="flex gap-2">
                                    <x-button type="button" size="sm" variant="ghost" x-on:click="editing = true">{{ __('Edit') }}</x-button>
                                    <x-confirm :action="route('results.weighting-schemes.items.destroy', $item->id)" size="sm" variant="ghost"
                                        :confirm="__('Remove')" :title="__('Remove this category weight?')">
                                        {{ __('Remove') }}
                                    </x-confirm>
                                </div>
                                <form method="POST" action="{{ route('results.weighting-schemes.items.update', $item->id) }}"
                                    x-show="editing" x-cloak class="flex flex-1 items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="assessment_category_id" value="{{ $item->assessment_category_id }}">
                                    <span class="text-sm text-gray-700">{{ $item->category?->name }}</span>
                                    <x-input name="weight_percentage" type="number" step="0.01" min="0.01" max="100" :value="$fmt($item->weight_percentage)" class="w-24" />
                                    <x-button type="submit" size="sm">{{ __('Save') }}</x-button>
                                    <x-button type="button" size="sm" variant="ghost" x-on:click="editing = false">{{ __('Cancel') }}</x-button>
                                </form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        @can('result.manage')
            <x-card :title="__('Add a category weight')">
                <form method="POST" action="{{ route('results.weighting-schemes.items.store', $scheme->id) }}" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    @csrf
                    <div class="space-y-1">
                        <label for="assessment_category_id" class="block text-sm font-medium text-gray-700">{{ __('Category') }}</label>
                        <select id="assessment_category_id" name="assessment_category_id"
                            class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                            <option value="">{{ __('Select a category') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('assessment_category_id') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('assessment_category_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <x-input name="weight_percentage" type="number" step="0.01" min="0.01" max="100" :label="__('Weight %')" :value="old('weight_percentage')" required />
                    <div class="flex items-end">
                        <x-button type="submit">{{ __('Add weight') }}</x-button>
                    </div>
                </form>
            </x-card>
        @endcan
    </div>
</x-layouts.authenticated>
