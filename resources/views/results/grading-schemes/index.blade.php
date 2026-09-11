<x-layouts.authenticated :title="__('Grading schemes')">
    <x-slot:actions>
        <x-button :href="route('results.runs.index')" size="sm" variant="secondary">{{ __('Results') }}</x-button>
    </x-slot:actions>

    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <p class="text-sm">
            <a href="{{ route('results.runs.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Results') }}</a>
        </p>

        <x-card :title="__('Grading schemes')">
            <p class="mb-4 text-sm text-gray-600">
                {{ __('A grading scheme is a set of percentage bands (e.g. A = 70-100). Add grade bands from a scheme\'s own page.') }}
            </p>

            @if ($schemes->isEmpty())
                <x-empty-state :title="__('No grading schemes yet')" :description="__('Add your first grading scheme below.')" />
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($schemes as $scheme)
                        <li class="flex items-center justify-between gap-3 py-3 first:pt-0">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900">
                                    <a href="{{ route('results.grading-schemes.show', $scheme->id) }}" class="hover:text-brand-700">{{ $scheme->name }}</a>
                                    @unless ($scheme->is_active) <x-badge variant="gray">{{ __('Inactive') }}</x-badge> @endunless
                                </p>
                                @if ($scheme->description) <p class="text-xs text-gray-500">{{ $scheme->description }}</p> @endif
                                <p class="text-xs text-gray-400">{{ trans_choice('{0}No grade bands|{1}:count grade band|[2,*]:count grade bands', $scheme->grades_count, ['count' => $scheme->grades_count]) }}</p>
                            </div>
                            <x-button :href="route('results.grading-schemes.show', $scheme->id)" size="sm" variant="secondary">{{ __('Open') }}</x-button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        @can('result.manage')
            <x-card :title="__('Add a grading scheme')">
                <form method="POST" action="{{ route('results.grading-schemes.store') }}" class="space-y-3">
                    @csrf
                    <x-input name="name" :label="__('Name')" :value="old('name')" required />
                    <x-input name="description" :label="__('Description (optional)')" :value="old('description')" />
                    <x-button type="submit">{{ __('Add scheme') }}</x-button>
                </form>
            </x-card>
        @endcan
    </div>
</x-layouts.authenticated>
