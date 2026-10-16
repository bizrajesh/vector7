<x-layouts.public :seo="$seo">
    <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight">{{ __('Compare properties') }}</h1>
        @if ($items->isEmpty())
            <div class="card card-pad mt-6"><p>{{ __('Tick “Compare” on up to :n search results to see them side by side.', ['n' => \App\Http\Controllers\Market\ShortlistController::MAX_COMPARE]) }}</p>
                <a href="{{ route('market.search') }}" class="btn-primary btn-pill mt-4">{{ __('Search properties') }}</a></div>
        @else
            @php
                $rows = [__('Type') => fn ($i) => $i['type_label'], __('Location') => fn ($i) => $i['location'],
                    __('Price') => fn ($i) => ($i['price_from'] ? __('From').' ' : '').\App\Support\Format::inr($i['price']).$i['price_suffix'].($i['offer_active'] ? ' ('.__('offer, was :price', ['price' => \App\Support\Format::inr($i['actual'])]).')' : ''),
                    __('Area') => fn ($i) => $i['area_sqft'] ? \App\Support\Format::num($i['area_sqft']).' '.__('sq ft') : '—',
                    __('Price per sq ft') => fn ($i) => $i['area_sqft'] && ! $i['price_from'] && $i['price_suffix'] === '' ? \App\Support\Format::inr($i['price'] / $i['area_sqft']) : '—',
                    __('Approval') => fn ($i) => $i['badge'] ?: '—', __('Features') => fn ($i) => implode(', ', array_map(fn ($n) => __($n), array_slice($i['feature_names'], 0, 10))) ?: '—', __('Listed by') => fn ($i) => $i['promoter']];
                $extra = $items->flatMap(fn ($i) => array_keys($i['details'] ?? []))->unique()->values();
            @endphp
            <div class="table-wrap mt-6">
                <table class="tbl min-w-[640px]">
                    <thead><tr><th class="w-40"><span class="sr-only">{{ __('Detail') }}</span></th>
                        @foreach ($items as $i)<th class="align-top">
                            @if ($i['image'])<img src="{{ $i['image'] }}" alt="" class="mb-2 aspect-[4/3] w-full max-w-[220px] rounded-lg object-cover" width="220" height="165" loading="lazy">@endif
                            <a href="{{ $i['url'] }}" class="font-extrabold">{{ $i['title'] }}</a>
                            <a href="{{ route('market.compare', ['keys' => $items->pluck('key')->reject(fn ($k) => $k === $i['key'])->implode(',')]) }}" class="mt-1 block text-xs font-normal text-muted">{{ __('Remove') }}</a>
                        </th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($rows as $label => $fn)<tr><th scope="row">{{ $label }}</th>@foreach ($items as $i)<td>{{ $fn($i) }}</td>@endforeach</tr>@endforeach
                        @foreach ($extra as $label)<tr><th scope="row">{{ $label }}</th>@foreach ($items as $i)<td>{{ $i['details'][$label] ?? '—' }}</td>@endforeach</tr>@endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.public>
