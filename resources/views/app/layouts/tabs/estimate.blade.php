@php $baseline = $layout->estimates->whereNotNull('locked_at')->sortBy('version')->first(); @endphp
<div class="grid gap-4 lg:grid-cols-3">
    <section class="card-pad lg:col-span-2">
        <h2 class="section-title mb-3">{{ $editable ? 'Live estimate' : 'Current estimate' }}</h2>
        <dl class="grid gap-y-2 text-sm sm:grid-cols-2">
            @foreach ([
                'Stage costs' => \App\Support\Money::inr($estimate['stage_cost']),
                'Facilities' => \App\Support\Money::inr($estimate['facility_cost']),
                'Land cost' => \App\Support\Money::inr($estimate['land_cost']),
                'Total project cost' => \App\Support\Money::inr($estimate['total_cost']),
                'Contingency' => $estimate['contingency_pct'].'%',
                'Production value' => \App\Support\Money::inr($estimate['production_value']),
                'Sellable area' => number_format($estimate['sellable_sqft']).' ft²',
                'Estimated plots' => $estimate['est_plots'],
                'Cost per sellable ft²' => \App\Support\Money::inr($estimate['cost_per_sellable_sqft'], true),
                'Planned timeline' => $estimate['total_days'].' days',
            ] as $label => $value)
                <div class="flex justify-between gap-4 border-b border-line-soft py-2 sm:mr-6"><dt class="text-ink-muted">{{ $label }}</dt><dd class="num font-semibold">{{ $value }}</dd></div>
            @endforeach
        </dl>
        <p class="help mt-3">Sellable price (rate per ft²) is set at launch; the project carries cost only.</p>
    </section>
    <section class="card-pad">
        <h2 class="section-title mb-3">Baseline</h2>
        @if ($baseline)
            <p class="text-sm">Locked v{{ $baseline->version }} on {{ $baseline->locked_at->format('j M Y') }}</p>
            <p class="kpi-value mt-2">@inr($baseline->production_value)</p>
            <p class="text-sm text-ink-muted">@inr($baseline->cost_per_sellable_sqft, true) per sellable ft² — used to compute realised profit on each plot sale.</p>
        @else
            <p class="text-sm text-ink-muted">The estimate is locked as baseline v1 when you submit the project.</p>
        @endif
    </section>
</div>
