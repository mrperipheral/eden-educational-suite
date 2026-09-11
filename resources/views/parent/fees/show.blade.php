@php
    $money = fn ($n) => number_format((float) $n, 2);
@endphp

<x-layouts.authenticated :title="__('Fees — :name', ['name' => $student->fullName()])">
    <div class="space-y-6">
        @include('parent._child-nav', [
            'student' => $student, 'siblings' => $siblings, 'active' => 'fees',
            'modules' => $modules, 'sectionRoute' => 'parent.fees.show',
        ])

        @include('fees._statement-body', ['readOnly' => true])
    </div>
</x-layouts.authenticated>
