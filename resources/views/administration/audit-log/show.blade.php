<x-layouts.authenticated :title="__('Audit entry')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('audit-log.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Audit Log') }}</a>
        </p>

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <x-badge variant="gray">{{ $log->event }}</x-badge>
            </div>

            <p class="mt-3 text-sm font-medium text-gray-900">{{ $log->summary }}</p>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium text-gray-500">{{ __('Date / time') }}</dt>
                    <dd class="text-sm text-gray-900">{{ $log->created_at?->format('d M Y, H:i:s') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500">{{ __('Actor') }}</dt>
                    <dd class="text-sm text-gray-900">{{ $log->actor_name ?? __('System / unauthenticated') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500">{{ __('Affected record') }}</dt>
                    <dd class="text-sm text-gray-900">
                        {{ $log->auditable_label ?? __('—') }}
                        @if ($log->auditable_type)
                            <span class="text-gray-400">({{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }})</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500">{{ __('IP address') }}</dt>
                    <dd class="text-sm text-gray-900">{{ $log->ip_address ?? __('—') }}</dd>
                </div>
            </dl>

            @if ($log->changes)
                <div class="mt-6">
                    <h3 class="text-xs font-medium text-gray-500">{{ __('Changes') }}</h3>
                    <div class="mt-2 grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold text-gray-600">{{ __('Before') }}</p>
                            <pre class="mt-1 overflow-x-auto rounded-md bg-gray-50 p-3 text-xs text-gray-700">{{ json_encode($log->changes['before'] ?? null, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-600">{{ __('After') }}</p>
                            <pre class="mt-1 overflow-x-auto rounded-md bg-gray-50 p-3 text-xs text-gray-700">{{ json_encode($log->changes['after'] ?? null, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    </div>
                </div>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
