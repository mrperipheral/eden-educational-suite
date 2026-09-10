@php
    $tabs = [
        ['route' => 'academic.sessions.index', 'label' => __('Sessions & terms'), 'active' => ['academic.sessions.*', 'academic.periods.*']],
        ['route' => 'academic.levels.index', 'label' => __('Levels & arms'), 'active' => ['academic.levels.*', 'academic.arms.*']],
        ['route' => 'academic.subjects.index', 'label' => __('Subjects'), 'active' => 'academic.subjects.*'],
    ];
@endphp

<nav class="flex flex-wrap gap-x-5 gap-y-2 border-b border-gray-200 pb-2 text-sm" aria-label="{{ __('Academic administration sections') }}">
    @foreach ($tabs as $tab)
        <a
            href="{{ route($tab['route']) }}"
            @class([
                'font-medium text-brand-700' => request()->routeIs($tab['active']),
                'text-gray-500 hover:text-gray-800' => ! request()->routeIs($tab['active']),
            ])
            @if (request()->routeIs($tab['active'])) aria-current="page" @endif
        >{{ $tab['label'] }}</a>
    @endforeach
</nav>
