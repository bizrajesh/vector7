<x-layouts.workspace title="Reports">
    <x-page-header title="Reports" subtitle="Pick a report, filter it, and download it as Excel or PDF." />
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($reports as $key => [$title, $desc, $filters, $icon])
            <a href="{{ route('ws.reports.show', $key) }}" class="card card-pad flex items-start gap-4 text-navy no-underline transition hover:ring-teal-200">
                <span class="rounded-xl bg-teal-50 p-2.5 text-teal-700"><x-icon :name="$icon" class="h-6 w-6" /></span>
                <span class="min-w-0"><span class="block font-bold">{{ $title }}</span><span class="mt-1 block text-sm text-muted">{{ $desc }}</span></span>
            </a>
        @endforeach
    </div>
</x-layouts.workspace>
