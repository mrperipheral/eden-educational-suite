@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<x-layouts.authenticated :title="__('Learning Materials')">
    @can('material.upload')
        <x-slot:actions>
            <x-button :href="route('learning-materials.create')" size="sm">{{ __('Upload material') }}</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <form method="GET" action="{{ route('learning-materials.index') }}" class="flex flex-wrap items-end gap-2">
            <div class="space-y-1">
                <label for="level" class="block text-xs font-medium text-gray-700">{{ __('Level') }}</label>
                <select id="level" name="level" class="{{ $selectClass }}" onchange="this.form.submit()">
                    <option value="">{{ __('All levels') }}</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->id }}" @selected(($filters['level'] ?? null) == $level->id)>{{ $level->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="subject" class="block text-xs font-medium text-gray-700">{{ __('Subject') }}</label>
                <select id="subject" name="subject" class="{{ $selectClass }}" onchange="this.form.submit()">
                    <option value="">{{ __('All subjects') }}</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected(($filters['subject'] ?? null) == $subject->id)>{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>
            @if (($filters['level'] ?? null) || ($filters['subject'] ?? null))
                <x-button :href="route('learning-materials.index')" variant="ghost" size="sm">{{ __('Clear') }}</x-button>
            @endif
        </form>

        @if ($materials->isEmpty())
            <x-empty-state
                :title="__('No learning materials yet')"
                :description="__('Upload a PDF, image or audio file for a class to see it here.')"
            >
                @can('material.upload')
                    <x-slot:actions>
                        <x-button :href="route('learning-materials.create')" size="sm">{{ __('Upload material') }}</x-button>
                    </x-slot:actions>
                @endcan
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($materials as $material)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    {{ $material->title }}
                                    <x-badge variant="gray" class="ml-1">{{ $material->type->label() }}</x-badge>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $material->subject?->name }} ·
                                    {{ $material->level?->name }}{{ $material->arm ? ' — '.$material->arm->name : '' }}
                                    ({{ $material->session?->name }})
                                    · {{ __('by :name', ['name' => $material->uploadedBy?->name ?? __('Unknown')]) }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <x-button :href="route('learning-materials.download', $material->id)" size="sm" variant="secondary">{{ __('Download') }}</x-button>
                                @can('material.upload')
                                    <x-confirm
                                        :action="route('learning-materials.destroy', $material->id)"
                                        method="DELETE"
                                        size="sm"
                                        :title="__('Delete this material?')"
                                        :message="__('This permanently removes the file. Students will no longer be able to access it.')"
                                        :confirm="__('Delete')"
                                    >
                                        {{ __('Delete') }}
                                    </x-confirm>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $materials->links() }}
        @endif
    </div>
</x-layouts.authenticated>
