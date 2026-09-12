@php
    $tabs = [
        ['route' => 'settings.school.edit', 'label' => __('Profile'), 'active' => 'settings.school.edit'],
        ['route' => 'settings.school.branding.edit', 'label' => __('Branding'), 'active' => 'settings.school.branding.*'],
        ['route' => 'settings.school.regional.edit', 'label' => __('Regional'), 'active' => 'settings.school.regional.*'],
        ['route' => 'settings.school.modules.edit', 'label' => __('Modules'), 'active' => 'settings.school.modules.*'],
        ['route' => 'settings.school.payments.edit', 'label' => __('Payments'), 'active' => 'settings.school.payments.*'],
    ];
@endphp

<nav class="flex flex-wrap gap-x-5 gap-y-2 border-b border-gray-200 pb-2 text-sm" aria-label="{{ __('School settings sections') }}">
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
