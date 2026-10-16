{{-- Search result card (spec 17.7). Vars: $item (PropertySearch item array), optional $layout ('grid'|'list'), $shortlisted (key map), $src ('ai') --}}
@php
    $layout = $layout ?? 'grid';
    $on = isset($shortlisted[$item['key']]);
    $href = $item['url'].(($src ?? null) === 'ai' ? '?src=ai' : '');
    $priceLabel = ($item['price_from'] ? 'From ' : '');
@endphp
<article class="card group relative flex overflow-hidden transition hover:shadow-lift {{ $layout === 'list' ? 'flex-col sm:flex-row' : 'flex-col' }}" data-card="{{ $item['key'] }}">
    <a href="{{ $href }}" class="relative block shrink-0 bg-page {{ $layout === 'list' ? 'sm:w-72' : '' }}" tabindex="-1" aria-hidden="true">
        @if ($item['image'])
            <img src="{{ $item['image'] }}" alt="" class="aspect-[4/3] w-full object-cover" loading="lazy" decoding="async" width="400" height="300">
        @else
            <span class="flex aspect-[4/3] w-full items-center justify-center text-navy-100"><x-icon name="building" class="h-12 w-12" /></span>
        @endif
        <span class="absolute left-2 top-2 flex flex-wrap gap-1">
            <span class="rounded-md bg-white/95 px-2 py-0.5 text-xs font-bold text-navy">{{ $item['kind'] === 'layout' ? 'Layout' : $item['type_label'] }}</span>
            @if ($item['offer_active'])<span class="rounded-md bg-teal-700 px-2 py-0.5 text-xs font-bold text-white">Offer</span>@endif
            @if ($item['featured'])<span class="rounded-md bg-gold px-2 py-0.5 text-xs font-bold text-navy">Featured</span>@endif
        </span>
        @if ($item['image_count'] > 1)<span class="absolute bottom-2 right-2 rounded-md bg-navy/80 px-2 py-0.5 text-xs text-white">{{ $item['image_count'] }} photos</span>@endif
    </a>
    <div class="flex min-w-0 flex-1 flex-col p-4">
        <div class="flex items-start justify-between gap-2">
            <h3 class="min-w-0 text-base font-extrabold leading-snug"><a href="{{ $href }}" class="text-navy no-underline after:absolute after:inset-0 hover:underline">{{ $item['title'] }}</a></h3>
            @auth('customer')
                <form method="POST" action="{{ route('account.shortlist.toggle') }}" class="relative z-10" data-shortlist>
                    @csrf<input type="hidden" name="key" value="{{ $item['key'] }}">
                    <button class="rounded-lg p-1.5 {{ $on ? 'text-gold' : 'text-muted hover:text-navy' }}" aria-pressed="{{ $on ? 'true' : 'false' }}" aria-label="{{ $on ? 'Remove from shortlist' : 'Add to shortlist' }}"><x-icon name="star" class="h-5 w-5" /></button>
                </form>
            @endauth
        </div>
        <p class="mt-0.5 flex items-center gap-1 text-sm text-muted"><x-icon name="location" class="h-4 w-4 shrink-0" /> <span class="truncate">{{ $item['location'] }}</span></p>
        <p class="mt-2">
            @if ($item['offer_active'] && $item['actual'] > $item['price'])
                <del class="text-sm text-muted">@inr($item['actual'])</del>
                <strong class="text-lg text-teal-700">{{ $priceLabel }}@inr($item['price'])</strong>{{ $item['price_suffix'] }}
            @else
                <strong class="text-lg">{{ $priceLabel }}@inr($item['price'])</strong>{{ $item['price_suffix'] }}
            @endif
        </p>
        @if ($item['specs'])<p class="mt-1 text-sm">{{ implode(' · ', $item['specs']) }}</p>@endif
        @if ($item['features'])
            <div class="mt-2 flex flex-wrap gap-1">@foreach ($item['features'] as $f)<span class="chip chip-sm"><x-icon :name="$f['icon']" class="h-3.5 w-3.5" /> {{ $f['name'] }}</span>@endforeach</div>
        @endif
        @if (! empty($item['why']))<p class="mt-2 rounded-lg bg-teal-50 px-2 py-1 text-xs text-teal-900"><x-icon name="sparkles" class="mr-1 inline h-3.5 w-3.5" />{{ $item['why'] }}</p>@endif
        <div class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-3 text-xs text-muted">
            <span>@if ($item['approved'])<span class="font-semibold text-teal-800"><x-icon name="shield" class="inline h-3.5 w-3.5" /> {{ $item['badge'] }}</span>@else{{ $item['promoter'] }}@endif</span>
            <label class="relative z-10 inline-flex items-center gap-1"><input type="checkbox" class="checkbox" value="{{ $item['key'] }}" data-compare> Compare</label>
        </div>
    </div>
</article>
