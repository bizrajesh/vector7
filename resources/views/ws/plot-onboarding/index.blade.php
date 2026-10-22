<x-layouts.workspace title="Plot onboarding">
    <x-page-header title="Plot onboarding" subtitle="Load all the plots of a layout from one Excel file: download the template, fill one row per plot, then upload it to the project.">
        <a href="{{ route('ws.plot-onboarding.template') }}" class="btn-primary"><x-icon name="download" class="h-4 w-4" /> Download template (.xlsx)</a>
    </x-page-header>

    <ol class="mb-6 grid gap-4 md:grid-cols-3">
        <li class="card card-pad">
            <p class="eyebrow">Step 1</p>
            <h2 class="mt-1 font-extrabold">Download the template</h2>
            <p class="mt-1 text-sm text-muted">The <strong>Plots</strong> sheet has the column headers, drop-down lists for facing, corner and status, and 3 sample rows. The <strong>How to fill</strong> sheet explains every column.</p>
            <a href="{{ route('ws.plot-onboarding.template') }}" class="mt-3 inline-flex text-sm font-semibold"><x-icon name="download" class="mr-1 h-4 w-4" /> vector7-plot-onboarding-template.xlsx</a>
        </li>
        <li class="card card-pad">
            <p class="eyebrow">Step 2</p>
            <h2 class="mt-1 font-extrabold">Fill one row per plot</h2>
            <p class="mt-1 text-sm text-muted">Keep the header names. Delete the sample rows. Status is <strong>Available</strong> or <strong>Blocked</strong> — Booked and Sold come from bookings and sales. Formulas are fine: their calculated values are read.</p>
        </li>
        <li class="card card-pad">
            <p class="eyebrow">Step 3</p>
            <h2 class="mt-1 font-extrabold">Upload and check</h2>
            <p class="mt-1 text-sm text-muted">You see every row with its errors before anything is saved. Fix them in the grid, then import. Uploading again updates plots with the same <code>plot_no</code>.</p>
        </li>
    </ol>

    <div class="grid gap-6 xl:grid-cols-5">
        <div class="card card-pad xl:col-span-2">
            <h2 class="section-title">Upload the filled template</h2>
            @if ($projects->isEmpty())
                <x-empty title="No project is ready for plots" icon="rocket">
                    Plots are loaded into a project that is Ready to Launch or Launched.
                    @can('quick_launch.create')<a href="{{ route('ws.quick-launch.create') }}" class="font-semibold">Direct launch</a> a layout (you can upload this file there too).@endcan
                    @if ($others > 0) or use <a href="{{ route('ws.launch.index') }}" class="font-semibold">Skip to launch</a> on one of your {{ $others }} project(s) in the project phase.@endif
                </x-empty>
            @else
                <form method="POST" action="{{ route('ws.plot-onboarding.upload') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <x-select name="project_id" label="Project" :options="$projects->mapWithKeys(fn ($p) => [$p->id => $p->name.' · '.$p->project_code.' · '.$p->plots_count.' plots · '.$p->statusLabel()])->all()" :value="old('project_id', $selected ?: null)" placeholder="Choose the project" required />
                    <x-field name="file" type="file" label="Filled template (.xlsx or .csv, up to 10 MB)" accept=".xlsx,.csv" required />
                    <button class="btn-primary w-full">Check & preview</button>
                    <p class="text-xs text-muted">Not listed? Only projects that are Ready to Launch or Launched take plots.
                        @can('quick_launch.create')New layout: <a href="{{ route('ws.quick-launch.create') }}">Direct launch</a>.@endcan
                        @if ($others > 0) In the project phase: <a href="{{ route('ws.launch.index') }}">Skip to launch</a>.@endif</p>
                </form>
            @endif
        </div>

        <div class="card xl:col-span-3">
            <h2 class="section-title p-4">Template columns</h2>
            <div class="table-wrap"><table class="tbl text-sm">
                <thead><tr><th>Column</th><th>Required</th><th>What to enter</th></tr></thead>
                <tbody>
                @foreach ([
                    'plot_no' => 'Plot number as on the approved layout (letters, digits, "-"). Must be unique.',
                    'patta_number' => 'Patta number of the plot. Kept in your workspace only — never shown to buyers.',
                    'size_sqft' => 'Area in sq ft. (Or give size_cents instead — 1 cent = 435.6 sq ft.)',
                    'length_ft' => 'Length in feet.',
                    'width_ft' => 'Width in feet.',
                    'facing' => implode(', ', \App\Models\Plot::FACINGS).'.',
                    'east_boundary' => 'What is on each side: plot number, road or land.',
                    'west_boundary' => '',
                    'north_boundary' => '',
                    'south_boundary' => '',
                    'road_width_ft' => 'Width of the road in front, in feet.',
                    'corner_plot' => 'Y or N.',
                    'rate_per_sqft' => 'Price per sq ft in ₹.',
                    'offer' => 'Offer text. If filled, offer_rate_per_sqft and offer_valid_till are needed.',
                    'offer_rate_per_sqft' => 'Offer price per sq ft — lower than rate_per_sqft.',
                    'offer_valid_till' => 'Last day of the offer, DD-MM-YYYY.',
                    'status' => 'Available or Blocked. (Or leave it empty and give plot_status: Available stays Available, Reserved / Allocated / anything else becomes Blocked.)',
                ] as $col => $help)
                    <tr><td class="font-mono text-xs">{{ $col }}</td><td>@if (($columns[$col] ?? 'No') === 'Yes')<span class="badge-navy">Yes</span>@else<span class="text-muted">No</span>@endif</td><td class="text-muted">{{ $help }}</td></tr>
                @endforeach
                </tbody>
            </table></div>
            <p class="p-4 text-xs text-muted">Buyers on the marketplace see only <strong>Available</strong>, <strong>Booked</strong> or <strong>Blocked</strong>. Every other stage (Sale Init, ROR, Sold…) shows as Blocked.</p>
        </div>
    </div>
</x-layouts.workspace>
