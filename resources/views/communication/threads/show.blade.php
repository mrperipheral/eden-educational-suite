@php
    $textareaClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<x-layouts.authenticated :title="$thread->subject">
    @if ($canManage)
        <x-slot:actions>
            <x-button :href="route('communication.threads.index')" variant="ghost" size="sm">{{ __('← All threads') }}</x-button>
        </x-slot:actions>
    @endif

    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <x-badge :variant="$thread->status->badgeVariant()">{{ $thread->status->label() }}</x-badge>
                <x-badge variant="gray">{{ $thread->category->label() }}</x-badge>
            </div>

            <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                @if ($thread->student)
                    <div><dt class="text-gray-500">{{ __('Student') }}</dt><dd class="text-gray-900">{{ $thread->student->displayName() }}</dd></div>
                @endif
                @if ($thread->guardian)
                    <div><dt class="text-gray-500">{{ __('Guardian') }}</dt><dd class="text-gray-900">{{ $thread->guardian->fullName() }}</dd></div>
                @endif
                <div><dt class="text-gray-500">{{ __('Created by') }}</dt><dd class="text-gray-900">{{ $thread->createdBy?->name }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Assigned to') }}</dt><dd class="text-gray-900">{{ $thread->assignedTo?->name ?? __('Unassigned') }}</dd></div>
            </dl>

            <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                @if ($thread->status !== \App\Enums\CommunicationStatus::Resolved && $canResolve)
                    <form method="POST" action="{{ route('communication.threads.resolve', $thread) }}">
                        @csrf
                        <x-button type="submit" size="sm" variant="secondary">{{ __('Mark resolved') }}</x-button>
                    </form>
                @endif
                @if ($thread->status !== \App\Enums\CommunicationStatus::Escalated && $canEscalate)
                    <form method="POST" action="{{ route('communication.threads.escalate', $thread) }}">
                        @csrf
                        <x-button type="submit" size="sm" variant="secondary">{{ __('Escalate') }}</x-button>
                    </form>
                @endif
                @if ($thread->status !== \App\Enums\CommunicationStatus::Open && $canResolve)
                    <form method="POST" action="{{ route('communication.threads.reopen', $thread) }}">
                        @csrf
                        <x-button type="submit" size="sm" variant="ghost">{{ __('Reopen') }}</x-button>
                    </form>
                @endif
            </div>
        </x-card>

        <x-card :title="__('Messages')" :padding="false">
            <ul class="divide-y divide-gray-100">
                @foreach ($thread->messages as $message)
                    <li class="px-4 py-3 sm:px-6">
                        <p class="text-xs text-gray-500">{{ $message->sender?->name }} · {{ $message->created_at->diffForHumans() }}</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $message->body }}</p>
                    </li>
                @endforeach
            </ul>
        </x-card>

        @can('communication.create')
            <x-card :title="__('Reply')">
                <form method="POST" action="{{ route('communication.threads.messages.store', $thread) }}" class="space-y-4">
                    @csrf
                    <textarea name="body" rows="4" class="{{ $textareaClass }}" required>{{ old('body') }}</textarea>
                    @error('body') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    <x-button type="submit">{{ __('Send reply') }}</x-button>
                </form>
            </x-card>
        @endcan
    </div>
</x-layouts.authenticated>
