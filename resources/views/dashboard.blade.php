<x-layouts.authenticated :title="__('Dashboard')">
    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @isset($onboarding)
            @if ($onboarding['complete'])
                <x-alert variant="success">{{ __('School onboarding is complete.') }}</x-alert>
            @else
                <x-card :title="__('Finish setting up :school', ['school' => $school->name])">
                    <ul class="space-y-2">
                        @foreach ($onboarding['steps'] as $step)
                            <li class="flex items-center gap-3 text-sm">
                                <span @class([
                                    'flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs',
                                    'bg-green-100 text-green-700' => $step['done'],
                                    'bg-gray-100 text-gray-400' => ! $step['done'],
                                ])>
                                    {{ $step['done'] ? '✓' : '' }}
                                </span>
                                @if ($step['done'])
                                    <span class="text-gray-500 line-through">{{ $step['label'] }}</span>
                                @else
                                    <a href="{{ $step['route'] }}" class="font-medium text-brand-600 hover:text-brand-700">{{ $step['label'] }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        @endisset

        <x-card :title="__('Current school')">
            <p class="text-sm text-gray-700">
                {{ __('You are working in') }}
                <strong>{{ $school->name }}</strong>.
            </p>
            <p class="mt-1 text-xs text-gray-500">
                {{ __('Everything you see and do is scoped to this school. Other schools\' data is never visible here.') }}
            </p>
        </x-card>

        @isset($administration)
            <x-card :title="__('Administration')">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <p class="text-2xl font-semibold text-gray-900">{{ $administration['activeMembers'] }}</p>
                        <p class="text-xs text-gray-500">{{ __('Active members') }}</p>
                    </div>
                    <div>
                        <p class="text-2xl font-semibold text-gray-900">{{ $administration['suspendedMembers'] }}</p>
                        <p class="text-xs text-gray-500">{{ __('Suspended / disabled members') }}</p>
                    </div>
                    <div>
                        <p class="text-2xl font-semibold text-gray-900">{{ $administration['modulesEnabled'] }} / {{ $administration['modulesTotal'] }}</p>
                        <p class="text-xs text-gray-500">{{ __('Modules enabled') }}</p>
                    </div>
                </div>

                <div class="mt-4 border-t border-gray-100 pt-4">
                    <p class="text-xs font-medium text-gray-500">{{ __('Recent activity') }}</p>
                    @if ($administration['recentActivity']->isEmpty())
                        <p class="mt-2 text-sm text-gray-500">{{ __('No administrative activity recorded yet.') }}</p>
                    @else
                        <ul class="mt-2 space-y-1">
                            @foreach ($administration['recentActivity'] as $log)
                                <li class="text-sm text-gray-700">
                                    <a href="{{ route('audit-log.show', $log->id) }}" class="hover:text-brand-700">{{ $log->summary }}</a>
                                    <span class="text-xs text-gray-400">— {{ $log->created_at?->diffForHumans() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <a href="{{ route('audit-log.index') }}" class="mt-2 inline-block text-xs font-medium text-brand-600 hover:text-brand-700">{{ __('View full audit log →') }}</a>
                </div>
            </x-card>
        @endisset

        <x-empty-state
            :title="__('Nothing to show yet')"
            :description="__('This is a placeholder dashboard. Once the school modules are built, this area will show what needs your attention in :school.', ['school' => $school->name])"
        >
            <x-slot:actions>
                <x-button :href="route('settings.profile.edit')" variant="secondary" size="sm">
                    {{ __('Account settings') }}
                </x-button>
            </x-slot:actions>
        </x-empty-state>
    </div>
</x-layouts.authenticated>
