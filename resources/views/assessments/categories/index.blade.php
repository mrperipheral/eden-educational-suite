<x-layouts.authenticated :title="__('Assessment categories')">
    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <p class="text-sm">
            <a href="{{ route('assessments.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Assessments') }}</a>
        </p>

        <x-card :title="__('Categories')">
            <p class="mb-4 text-sm text-gray-600">
                {{ __('Categories group assessments (for example Classwork, Test, Exam). They are yours to configure — rename, reorder or deactivate any of them.') }}
            </p>

            @if ($categories->isEmpty())
                <x-empty-state :title="__('No categories yet')" :description="__('Add your first assessment category below.')" />
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($categories as $category)
                        <li class="py-3 first:pt-0" x-data="{ editing: false }">
                            <div class="flex items-start justify-between gap-3" x-show="!editing">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900">
                                        {{ $category->name }}
                                        @if ($category->code) <span class="font-mono text-xs text-gray-400">{{ $category->code }}</span> @endif
                                        @unless ($category->is_active) <x-badge variant="gray">{{ __('Inactive') }}</x-badge> @endunless
                                    </p>
                                    @if ($category->description) <p class="text-xs text-gray-500">{{ $category->description }}</p> @endif
                                    <p class="text-xs text-gray-400">{{ trans_choice('{0}No assessments|{1}:count assessment|[2,*]:count assessments', $category->assessments_count, ['count' => $category->assessments_count]) }}</p>
                                </div>
                                @can('assessment.manage')
                                    <x-button type="button" size="sm" variant="ghost" x-on:click="editing = true">{{ __('Edit') }}</x-button>
                                @endcan
                            </div>

                            @can('assessment.manage')
                                <form method="POST" action="{{ route('assessments.categories.update', $category->id) }}"
                                    x-show="editing" x-cloak class="mt-2 space-y-2">
                                    @csrf
                                    @method('PATCH')
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                        <x-input name="name" :label="__('Name')" :value="$category->name" required />
                                        <x-input name="code" :label="__('Code')" :value="$category->code" />
                                        <x-input name="position" type="number" min="0" :label="__('Order')" :value="$category->position" />
                                    </div>
                                    <x-input name="description" :label="__('Description')" :value="$category->description" />
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="is_active" value="1" @checked($category->is_active)
                                            class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                        {{ __('Active') }}
                                    </label>
                                    <div class="flex gap-2">
                                        <x-button type="submit" size="sm">{{ __('Save') }}</x-button>
                                        <x-button type="button" size="sm" variant="ghost" x-on:click="editing = false">{{ __('Cancel') }}</x-button>
                                    </div>
                                </form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        @can('assessment.manage')
            <x-card :title="__('Add a category')">
                <form method="POST" action="{{ route('assessments.categories.store') }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <x-input name="name" :label="__('Name')" :value="old('name')" required />
                        <x-input name="code" :label="__('Code (optional)')" :value="old('code')" />
                        <x-input name="position" type="number" min="0" :label="__('Order')" :value="old('position', 0)" />
                    </div>
                    <x-input name="description" :label="__('Description (optional)')" :value="old('description')" />
                    <x-button type="submit">{{ __('Add category') }}</x-button>
                </form>
            </x-card>
        @endcan
    </div>
</x-layouts.authenticated>
