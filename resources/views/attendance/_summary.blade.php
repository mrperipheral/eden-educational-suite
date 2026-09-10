{{-- @var array $summary  ['total','present','absent','late','excused','unmarked'] --}}
<div class="flex flex-wrap gap-2 text-xs">
    <span class="rounded-md bg-gray-100 px-2 py-1 font-medium text-gray-700">{{ __(':n students', ['n' => $summary['total']]) }}</span>
    <span class="rounded-md bg-green-100 px-2 py-1 font-medium text-green-700">{{ __('Present') }} {{ $summary['present'] }}</span>
    <span class="rounded-md bg-red-100 px-2 py-1 font-medium text-red-700">{{ __('Absent') }} {{ $summary['absent'] }}</span>
    <span class="rounded-md bg-amber-100 px-2 py-1 font-medium text-amber-700">{{ __('Late') }} {{ $summary['late'] }}</span>
    <span class="rounded-md bg-gray-100 px-2 py-1 font-medium text-gray-600">{{ __('Excused') }} {{ $summary['excused'] }}</span>
    @if ($summary['unmarked'] > 0)
        <span class="rounded-md bg-yellow-100 px-2 py-1 font-medium text-yellow-800">{{ __('Unmarked') }} {{ $summary['unmarked'] }}</span>
    @endif
</div>
