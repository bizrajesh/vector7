<x-layouts.workspace title="Posts & promo codes">
    <x-page-header title="Social posts & promo codes" subtitle="Write a post for a launched project with Claude and attach a promo code. Buyers enter the code when booking." />
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @can('promos.create')
                <form method="POST" action="{{ route('ws.promos.generate') }}" class="card card-pad grid gap-3 sm:grid-cols-2">
                    @csrf
                    <h2 class="section-title sm:col-span-2">New post</h2>
                    <x-select name="project_id" label="Project" :options="$projects" required placeholder="Choose a launched project" />
                    <x-select name="platform" label="Platform" :options="\App\Services\SocialService::PLATFORMS" />
                    <x-select name="promo_code_id" label="Attach promo code" :options="$codes->where('is_active', true)->pluck('code', 'id')" placeholder="None" />
                    <x-field name="brief" label="Anything to highlight?" placeholder="Diwali weekend site visit, free registration…" />
                    <div class="sm:col-span-2"><button class="btn-primary"><x-icon name="sparkles" class="h-4 w-4" /> Write post</button></div>
                </form>
            @endcan
            @forelse ($posts as $post)
                <div class="card card-pad">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-bold">{{ \App\Services\SocialService::PLATFORMS[$post->platform] }} · {{ $post->project?->name }}</p>
                        <div class="flex items-center gap-2">@if ($post->promoCode)<span class="badge-amber">{{ $post->promoCode->code }}</span>@endif<span class="badge-gray">{{ ucfirst($post->status) }}</span></div>
                    </div>
                    <form method="POST" action="{{ route('ws.promos.post.update', $post) }}" class="mt-3 space-y-2">
                        @csrf @method('PUT')
                        <textarea id="cap{{ $post->id }}" name="caption" rows="5" class="input" aria-label="Caption" @cannot('promos.update') readonly @endcannot>{{ $post->caption }}</textarea>
                        <input name="hashtags" value="{{ $post->hashtags }}" class="input" aria-label="Hashtags" @cannot('promos.update') readonly @endcannot>
                        <p class="text-xs text-muted">Image idea: {{ $post->image_suggestion }}</p>
                        <textarea id="full{{ $post->id }}" class="sr-only" tabindex="-1" aria-hidden="true">{{ $post->caption }}

{{ $post->hashtags }}</textarea>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn-teal btn-sm" data-copy="full{{ $post->id }}"><x-icon name="copy" class="h-4 w-4" /> Copy post</button>
                            @can('promos.update')<button class="btn-light btn-sm">Save edits</button>@endcan
                        </div>
                    </form>
                    @can('promos.delete')<div class="mt-2"><x-confirm :action="route('ws.promos.post.destroy', $post)" method="DELETE" message="Delete this post?" class="btn-ghost btn-sm text-red-700">Delete</x-confirm></div>@endcan
                </div>
            @empty
                <div class="card"><x-empty title="No posts yet" icon="megaphone" /></div>
            @endforelse
            {{ $posts->links() }}
        </div>
        <div class="space-y-6">
            <div class="card">
                <h2 class="section-title p-4">Promo codes</h2>
                <ul class="divide-y divide-navy-50">
                    @forelse ($codes as $c)
                        <li class="p-4 text-sm">
                            <div class="flex items-center justify-between"><span class="font-mono text-base font-bold">{{ $c->code }}</span><span class="{{ $c->is_active && $c->valid_to->gte(today()) ? 'badge-teal' : 'badge-gray' }}">{{ $c->is_active && $c->valid_to->gte(today()) ? 'Active' : 'Inactive' }}</span></div>
                            <p class="text-muted">{{ $c->discount_type === 'percent' ? (float) $c->value.'% off' : \App\Support\Format::inr($c->value).' off' }} · @date($c->valid_from) – @date($c->valid_to)</p>
                            <p class="text-muted">Used {{ $c->used_count }}{{ $c->max_uses ? ' of '.$c->max_uses : '' }} time(s)</p>
                            @can('promos.update')
                                <details class="mt-2"><summary class="cursor-pointer text-xs font-semibold text-teal-700">Edit</summary>
                                    <form method="POST" action="{{ route('ws.promos.codes.update', $c) }}" class="mt-2 grid grid-cols-2 gap-2">
                                        @csrf @method('PUT')
                                        @include('ws.promos.code-fields', ['c' => $c, 'p' => 'c'.$c->id])
                                        <input type="hidden" name="is_active" value="0"><x-checkbox name="is_active" label="Active" :checked="$c->is_active" class="col-span-2" />
                                        <button class="btn-primary btn-sm col-span-2">Save</button>
                                    </form>
                                </details>
                            @endcan
                        </li>
                    @empty
                        <li class="p-4 text-sm text-muted">No promo codes.</li>
                    @endforelse
                </ul>
            </div>
            @can('promos.create')
                <form method="POST" action="{{ route('ws.promos.codes.store') }}" class="card card-pad grid grid-cols-2 gap-3">
                    @csrf
                    <h2 class="section-title col-span-2">New promo code</h2>
                    @include('ws.promos.code-fields', ['c' => new \App\Models\PromoCode(['discount_type' => 'percent', 'valid_from' => today(), 'valid_to' => today()->addDays(30)]), 'p' => 'nc'])
                    <button class="btn-primary col-span-2">Create code</button>
                </form>
            @endcan
        </div>
    </div>
</x-layouts.workspace>
