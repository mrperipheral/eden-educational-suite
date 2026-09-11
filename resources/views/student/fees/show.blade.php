<x-layouts.authenticated :title="__('My Fees')">
    <div class="space-y-6">
        @include('student._nav', ['active' => 'fees', 'modules' => $modules])

        @include('fees._statement-body', ['readOnly' => true])
    </div>
</x-layouts.authenticated>
