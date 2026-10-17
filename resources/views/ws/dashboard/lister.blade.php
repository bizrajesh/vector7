<x-layouts.workspace title="Dashboard">
    <x-page-header :title="'Hello, '.\Illuminate\Support\Str::before($user->name, ' ')" subtitle="Your listings at a glance: who is looking, who asked for a call back and who unlocked your number.">
        @can('listings.create')<a href="{{ route('ws.listings.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Post a property</a>@endcan
    </x-page-header>

    @if ($sub && ! $sub->isUsable())
        <div class="mb-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">Your plan has ended. You can keep {{ \App\Services\PlanLimiter::freeListings() }} listings live for free. <a href="{{ route('ws.settings.plan') }}" class="font-semibold underline">Renew or choose a plan</a> to publish more.</div>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-6">
        <x-stat label="Live listings" :value="$stats['live']" icon="building" />
        <x-stat label="Drafts" :value="$stats['draft']" icon="edit" tone="navy" />
        <x-stat label="Views" :value="\App\Support\Format::num($stats['views'])" icon="eye" tone="navy" />
        <x-stat label="Enquiries" :value="$stats['enquiries']" icon="chat" />
        <x-stat label="Open call-backs" :value="$stats['callbacks']" icon="phone" tone="gold" />
        <x-stat label="Number unlocked" :value="$stats['unlocks']" icon="lock" tone="navy" />
    </div>

    <div class="card card-pad mt-6">
        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <p><strong>{{ $usage['display']['used'] }}</strong> of {{ $usage['display']['limit'] }} active listings used · {{ $sub?->plan?->name ?? 'No plan' }}</p>
            <a href="{{ route('ws.settings.plan') }}" class="font-semibold">Plan & top-ups →</a>
        </div>
        @unless ($usage['unlimited'])<x-progress :pct="$usage['pct']" class="mt-2" label="Active listings used" />@endunless
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="flex items-center justify-between p-4"><h2 class="section-title">My listings</h2><a href="{{ route('ws.listings.index') }}" class="text-sm font-semibold">All listings →</a></div>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>Listing</th><th>Status</th><th class="num">Views</th><th class="num">Enquiries</th><th class="num">Unlocked</th><th></th></tr></thead>
                <tbody>
                @forelse ($listings->take(12) as $l)
                    <tr>
                        <td><a href="{{ route('ws.listings.edit', $l) }}" class="font-semibold">{{ $l->title }}</a><p class="text-xs text-muted">{{ $l->listing_code }} · {{ $l->typeLabel() }} · {{ $l->city }}</p></td>
                        <td><span class="{{ ['published' => 'badge-teal', 'under_offer' => 'badge-amber', 'sold' => 'badge-navy', 'draft' => 'badge-gray', 'unpublished' => 'badge-gray'][$l->status] ?? 'badge-gray' }}">{{ $l->statusLabel() }}</span></td>
                        <td class="num">{{ \App\Support\Format::num($l->views) }}</td>
                        <td class="num">{{ $leadCounts[$l->id] ?? 0 }}</td>
                        <td class="num">{{ $unlocks[$l->id] ?? 0 }}</td>
                        <td class="text-right whitespace-nowrap">@if ($l->isLive())<a href="{{ $l->publicUrl() }}" target="_blank" rel="noopener" class="btn-ghost btn-sm">View</a>@endif<a href="{{ route('ws.listings.edit', $l) }}" class="btn-light btn-sm">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty title="No listings yet" icon="building">Post your first property — flat, house, villa, plot, farm land or shop. It goes live as soon as you publish it.</x-empty></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="card">
            <div class="flex items-center justify-between p-4"><h2 class="section-title">Latest enquiries</h2><a href="{{ route('ws.leads.index') }}" class="text-sm font-semibold">All →</a></div>
            <ul class="divide-y divide-navy-50">
                @forelse ($leads as $lead)
                    <li class="px-4 py-3">
                        <a href="{{ route('ws.leads.show', $lead) }}" class="font-semibold">{{ $lead->name }}</a> <span class="text-xs text-muted">· {{ $lead->sourceLabel() }}</span>
                        <p class="text-xs text-muted">{{ $lead->mobile }} · {{ $lead->interests->map->label()->filter()->implode(', ') ?: '—' }}</p>
                        @if ($lead->preferred_mode)<p class="text-xs font-semibold text-teal-700">Call back: {{ \App\Http\Controllers\Market\ContactController::CALLBACK_MODES[$lead->preferred_mode] ?? '' }}{{ $lead->preferred_time ? ' · '.$lead->preferred_time : '' }}</p>@endif
                    </li>
                @empty
                    <li class="px-4 py-6 text-sm text-muted">Enquiries and call-back requests from buyers appear here.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-layouts.workspace>
