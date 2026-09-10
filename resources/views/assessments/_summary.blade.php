{{-- @var array $summary  ['total','entered','unentered','highest','lowest'] --}}
<div class="flex flex-wrap gap-2 text-xs">
    <span class="rounded-md bg-gray-100 px-2 py-1 font-medium text-gray-700">{{ trans_choice('{0}No students|{1}:count student|[2,*]:count students', $summary['total'], ['count' => $summary['total']]) }}</span>
    <span class="rounded-md bg-green-100 px-2 py-1 font-medium text-green-700">{{ __('Entered') }} {{ $summary['entered'] }}</span>
    @if ($summary['unentered'] > 0)
        <span class="rounded-md bg-yellow-100 px-2 py-1 font-medium text-yellow-800">{{ __('Not entered') }} {{ $summary['unentered'] }}</span>
    @endif
    @if ($summary['highest'] !== null)
        <span class="rounded-md bg-gray-100 px-2 py-1 font-medium text-gray-600">{{ __('High') }} {{ rtrim(rtrim(number_format($summary['highest'], 2), '0'), '.') }}</span>
        <span class="rounded-md bg-gray-100 px-2 py-1 font-medium text-gray-600">{{ __('Low') }} {{ rtrim(rtrim(number_format($summary['lowest'], 2), '0'), '.') }}</span>
    @endif
</div>
