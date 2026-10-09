{{--
    Interactive plot map. Plots are drawn over the layout picture (DXF outlines or pins placed in Launch),
    coloured by status; every shape/tile shows the plot tooltip on hover, focus or tap.
    Vars: $project, $plots, $layoutUrl (image URL or null)
--}}
@php
    $W = (int) ($project->layout_width ?: 1000);
    $H = (int) ($project->layout_height ?: 700);
    $positioned = $plots->filter(fn ($p) => $p->map_polygon || $p->map_x !== null);
    $isImage = $layoutUrl && $project->layoutFile && str_starts_with($project->layoutFile->mime, 'image/');
@endphp
<div class="plot-map">
    @if ($isImage)
        <div class="relative overflow-hidden rounded-xl bg-page ring-1 ring-navy-50">
            <img src="{{ $layoutUrl }}" alt="Layout plan of {{ $project->name }}" class="block h-auto w-full" width="{{ $W }}" height="{{ $H }}" loading="lazy" decoding="async">
            @if ($positioned->isNotEmpty())
                <svg class="absolute inset-0 h-full w-full" viewBox="0 0 {{ $W }} {{ $H }}" preserveAspectRatio="xMidYMid meet" role="group" aria-label="Plots on the layout">
                    @foreach ($positioned as $p)
                        @php($c = $p->color())
                        <g class="plot-shape" data-plot='@json($p->tooltipData())' tabindex="0" role="button" aria-label="Plot {{ $p->plot_no }}, {{ $p->statusLabel() }}, {{ \App\Support\Format::num($p->size_sqft) }} sq ft, {{ \App\Support\Format::inr($p->currentPrice()) }}">
                            @if ($p->map_polygon)
                                <polygon points="{{ collect($p->map_polygon)->map(fn ($pt) => round($pt[0] / 100 * $W, 1).','.round($pt[1] / 100 * $H, 1))->implode(' ') }}" fill="{{ $c }}" fill-opacity="0.45" stroke="{{ $c }}" stroke-width="2" />
                            @else
                                @php($cx = round($p->map_x / 100 * $W, 1))
                                @php($cy = round($p->map_y / 100 * $H, 1))
                                @php($r = max(10, round(min($W, $H) / 45)))
                                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="{{ $c }}" fill-opacity="0.9" stroke="#FFFFFF" stroke-width="2" />
                                <text x="{{ $cx }}" y="{{ $cy }}" text-anchor="middle" dominant-baseline="central" font-size="{{ round($r * 0.9) }}" font-weight="700" fill="#FFFFFF">{{ $p->plot_no }}</text>
                            @endif
                        </g>
                    @endforeach
                </svg>
            @endif
        </div>
    @elseif ($layoutUrl)
        <a href="{{ $layoutUrl }}" class="btn-light mb-3" target="_blank" rel="noopener"><x-icon name="doc" class="h-4 w-4" /> Open layout plan (PDF)</a>
    @endif

    @if (! $isImage || $positioned->isEmpty())
        {{-- Schematic map: every plot as a tile, coloured by status --}}
        <div class="mt-3 grid grid-cols-6 gap-1.5 sm:grid-cols-10 lg:grid-cols-12" role="group" aria-label="Plot map">
            @foreach ($plots as $p)
                <div class="plot-shape flex aspect-square items-center justify-center rounded-md text-xs font-bold text-white ring-1 ring-white/40 focus:outline-none focus:ring-2 focus:ring-navy {{ ['available' => 'bg-teal-700', 'booked' => 'bg-amber-600', 'sale_init' => 'bg-blue-600', 'ror' => 'bg-violet-600', 'ror_init' => 'bg-purple-600', 'ror_completed' => 'bg-indigo-700', 'sold' => 'bg-slate-600', 'blocked' => 'bg-red-600'][$p->status] ?? 'bg-slate-500' }}"
                     data-plot='@json($p->tooltipData())' tabindex="0" role="button" aria-label="Plot {{ $p->plot_no }}, {{ $p->statusLabel() }}">{{ $p->plot_no }}</div>
            @endforeach
        </div>
    @endif

    <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" aria-label="Legend">
        @foreach (\App\Models\Plot::STATUSES as $k => $l)
            @if ($plots->contains('status', $k))
                <li class="flex items-center gap-1.5"><svg class="h-3 w-3" viewBox="0 0 10 10" aria-hidden="true"><rect width="10" height="10" rx="2" fill="{{ \App\Models\Plot::COLORS[$k] }}" /></svg>{{ $l }}</li>
            @endif
        @endforeach
    </ul>
</div>
