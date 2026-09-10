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
