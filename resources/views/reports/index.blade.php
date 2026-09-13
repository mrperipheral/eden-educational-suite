<x-layouts.authenticated :title="__('Reports')">
    <div class="space-y-6">
        <x-alert variant="info">
            {{ __('Dashboard analytics and administrative reports drawn from your school\'s own enabled modules. Each area below respects your existing permissions.') }}
        </x-alert>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if (empty($sections))
            <x-empty-state
                :title="__('No reports available yet')"
                :description="__('Reports appear here once the relevant modules are enabled and you hold the matching permission.')"
            />
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($sections as $section)
                    <x-card>
                        <h3 class="text-sm font-semibold text-gray-900">
                            <a href="{{ route($section['route']) }}" class="hover:text-brand-700">{{ $section['label'] }}</a>
                        </h3>
                        <p class="mt-1 text-xs text-gray-500">{{ $section['description'] }}</p>
                        <div class="mt-3">
                            <x-button :href="route($section['route'])" size="sm" variant="secondary">{{ __('Open') }}</x-button>
                        </div>
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.authenticated>
