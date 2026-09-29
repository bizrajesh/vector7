@if (session('status'))
    <div role="status" class="mb-4 flex items-start gap-2 rounded-xl border border-sage/40 bg-sage-100 px-4 py-3 text-sm text-[#2F4A28]">
        <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0" stroke="2.4" /> <span>{{ session('status') }}</span>
    </div>
@endif
@if (session('error'))
    <div role="alert" class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ session('error') }}</span>
    </div>
@endif
@if ($errors->any())
    <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <p class="font-semibold">Please fix the following:</p>
        <ul class="mt-1 list-disc pl-5">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif
