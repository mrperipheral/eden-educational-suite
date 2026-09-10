@php use App\Enums\Role; @endphp

<x-layouts.authenticated :title="__('Members')">
    @if ($canAssign)
        <x-slot:actions>
            <x-button :href="route('members.create')" size="sm">{{ __('Add member') }}</x-button>
        </x-slot:actions>
    @endif

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('members.index') }}"
                @class([
                    'rounded-md px-2.5 py-1 text-xs font-medium',
                    'bg-brand-600 text-white' => ! $roleFilter,
                    'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' => (bool) $roleFilter,
                ])
            >{{ __('All') }}</a>

            @foreach (Role::all() as $role)
                <a
                    href="{{ route('members.index', ['role' => $role->value]) }}"
                    @class([
                        'rounded-md px-2.5 py-1 text-xs font-medium',
                        'bg-brand-600 text-white' => $roleFilter === $role,
                        'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50' => $roleFilter !== $role,
                    ])
                >{{ $role->label() }}</a>
            @endforeach
        </div>

        @if ($members->isEmpty())
            <x-empty-state
                :title="__('No members')"
                :description="$roleFilter
                    ? __('No members with this role yet.')
                    : __('This school has no members yet.')"
            />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($members as $member)
                        <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    {{ $member->name }}
                                    @if ($member->id === auth()->id())
                                        <span class="text-xs font-normal text-gray-400">({{ __('you') }})</span>
                                    @endif
                                </p>
                                <p class="truncate text-xs text-gray-500">{{ $member->email }}</p>
                            </div>

                            <div class="flex items-center gap-2">
                                @php($currentRole = $member->pivot->role)

                                @if ($canAssign && $member->id !== auth()->id() && count($assignableRoles))
                                    <form method="POST" action="{{ route('members.update-role', $member) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select
                                            name="role"
                                            class="rounded-md border-0 py-1 pl-2 pr-8 text-xs text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500"
                                        >
                                            @foreach ($assignableRoles as $role)
                                                <option value="{{ $role->value }}" @selected($currentRole === $role)>{{ $role->label() }}</option>
                                            @endforeach
                                        </select>
                                        <x-button type="submit" size="sm" variant="secondary">{{ __('Update') }}</x-button>
                                    </form>
                                @else
                                    <x-badge :variant="$currentRole ? 'brand' : 'gray'">
                                        {{ $currentRole?->label() ?? __('No role') }}
                                    </x-badge>
                                @endif

                                @if ($canRemove && $member->id !== auth()->id())
                                    <x-confirm
                                        :action="route('members.destroy', $member)"
                                        method="DELETE"
                                        :title="__('Remove member?')"
                                        :message="__(':name will lose access to this school.', ['name' => $member->name])"
                                        :confirm="__('Remove')"
                                        size="sm"
                                    >{{ __('Remove') }}</x-confirm>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $members->links() }}
        @endif
    </div>
</x-layouts.authenticated>
