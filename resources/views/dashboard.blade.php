<x-layouts.authenticated :title="__('Dashboard')">
    <div class="space-y-6">
        <x-greeting :context="__('Here\'s what\'s happening at :school today.', ['school' => $school->name])" />

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
            @if ($school->settings?->motto)
                <p class="mt-1 text-sm italic text-gray-500">&ldquo;{{ $school->settings->motto }}&rdquo;</p>
            @endif
            <p class="mt-1 text-xs text-gray-500">
                {{ __('Everything you see and do is scoped to this school. Other schools\' data is never visible here.') }}
            </p>
        </x-card>

        @isset($todayClasses)
            <x-card :title="__('Your classes today')">
                @if ($todayClasses->isEmpty())
                    <x-empty-state :title="__('No classes scheduled for you today')" />
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($todayClasses as $entry)
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-gray-900">{{ $entry->subject?->name }}</p>
                                    <p class="truncate text-xs text-gray-500">
                                        {{ $entry->level?->name }}{{ $entry->arm ? ' — '.$entry->arm->name : '' }}
                                        @if ($entry->room) · {{ $entry->room }} @endif
                                    </p>
                                </div>
                                <span class="shrink-0 font-mono text-xs text-gray-500">{{ $entry->start_time }}–{{ $entry->end_time }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        @endisset

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

        @if (! empty($kpis))
            <div class="space-y-4">
                @can('reports.view')
                    <p class="text-sm">
                        <a href="{{ route('reports.index') }}" class="font-medium text-brand-600 hover:text-brand-700">{{ __('View all reports →') }}</a>
                    </p>
                @endcan

                @if (isset($kpis['students']))
                    <x-card :title="__('Students')">
                        <div class="grid gap-4 sm:grid-cols-5">
                            <div><p class="text-2xl font-semibold text-gray-900">{{ $kpis['students']['total'] }}</p><p class="text-xs text-gray-500">{{ __('Total') }}</p></div>
                            <div><p class="text-2xl font-semibold text-green-700">{{ $kpis['students']['active'] }}</p><p class="text-xs text-gray-500">{{ __('Active') }}</p></div>
                            <div><p class="text-2xl font-semibold text-gray-900">{{ $kpis['students']['inactive'] }}</p><p class="text-xs text-gray-500">{{ __('Inactive') }}</p></div>
                            <div><p class="text-2xl font-semibold text-gray-900">{{ $kpis['students']['withdrawn'] }}</p><p class="text-xs text-gray-500">{{ __('Withdrawn') }}</p></div>
                            <div><p class="text-2xl font-semibold text-gray-900">{{ $kpis['students']['graduated'] }}</p><p class="text-xs text-gray-500">{{ __('Graduated') }}</p></div>
                        </div>
                    </x-card>
                @endif

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @if (isset($kpis['staff']))
                        <x-card :title="__('Staff')">
                            <p class="text-2xl font-semibold text-gray-900">{{ $kpis['staff']['active'] }} <span class="text-sm font-normal text-gray-500">/ {{ $kpis['staff']['total'] }}</span></p>
                            <p class="text-xs text-gray-500">{{ __('Active teachers') }}</p>
                        </x-card>
                    @endif

                    @if (isset($kpis['attendance']))
                        <x-card :title="__('Attendance (last 30 days)')">
                            <p class="text-2xl font-semibold text-gray-900">{{ $kpis['attendance']['attendance_percentage'] ?? '—' }}{{ $kpis['attendance']['attendance_percentage'] !== null ? '%' : '' }}</p>
                            <p class="text-xs text-gray-500">{{ __(':n registers submitted', ['n' => $kpis['attendance']['registers_submitted']]) }}</p>
                        </x-card>
                    @endif

                    @if (isset($kpis['results']))
                        <x-card :title="__('Results')">
                            <p class="text-2xl font-semibold text-gray-900">{{ $kpis['results']['runs'] }}</p>
                            <p class="text-xs text-gray-500">{{ __(':published published · :locked locked', ['published' => $kpis['results']['published'], 'locked' => $kpis['results']['locked']]) }}</p>
                        </x-card>
                    @endif

                    @if (isset($kpis['fees']))
                        <x-card :title="__('Fees')">
                            <p class="text-2xl font-semibold text-red-700">{{ $kpis['fees']['total_outstanding'] }}</p>
                            <p class="text-xs text-gray-500">{{ __('Outstanding') }} · {{ __('Collected') }}: {{ $kpis['fees']['total_collected'] }}</p>
                        </x-card>
                    @endif

                    @if (isset($kpis['cbt']))
                        <x-card :title="__('CBT')">
                            <p class="text-2xl font-semibold text-gray-900">{{ $kpis['cbt']['completed'] }} <span class="text-sm font-normal text-gray-500">/ {{ $kpis['cbt']['attempts'] }}</span></p>
                            <p class="text-xs text-gray-500">{{ __(':n examinations · :p passed', ['n' => $kpis['cbt']['examinations'], 'p' => $kpis['cbt']['passed']]) }}</p>
                        </x-card>
                    @endif
                </div>
            </div>
        @else
            <x-empty-state
                :title="__('Nothing to show yet')"
                :description="__('You don\'t currently have access to any reporting cards for :school — ask a School Admin if you believe this is wrong.', ['school' => $school->name])"
            >
                <x-slot:actions>
                    <x-button :href="route('settings.profile.edit')" variant="secondary" size="sm">
                        {{ __('Account settings') }}
                    </x-button>
                </x-slot:actions>
            </x-empty-state>
        @endif
    </div>
</x-layouts.authenticated>
