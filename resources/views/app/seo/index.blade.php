<x-layouts.workspace title="SEO">
    <x-page-header title="SEO" subtitle="Titles (≤ 60 characters), descriptions (≤ 155), robots.txt and the XML sitemap for the public marketplace.">
        @can('seo.update')
            <form method="POST" action="{{ route('app.seo.build') }}" class="flex flex-wrap items-center gap-2">@csrf
                <input type="hidden" name="ai" value="0"><x-checkbox name="ai" label="Write with Claude" />
                <button class="btn-primary"><x-icon name="refresh" class="h-4 w-4" /> Rebuild now</button>
            </form>
        @endcan
    </x-page-header>
    @unless ($indexable)
        <div class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200"><strong>Staging mode:</strong> SEO_INDEXABLE=false in .env — robots.txt disallows everything and every page is noindex.</div>
    @endunless
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <div class="card">
                <div class="p-4"><h2 class="section-title">Page meta</h2><p class="text-xs text-muted">Edited rows are locked so “Rebuild” keeps your wording.</p></div>
                <form method="GET" class="flex flex-wrap gap-2 px-4 pb-4" role="search">
                    <x-select name="type" :options="['page' => 'Static pages', 'project' => 'Projects', 'plot' => 'Plots']" :value="request('type')" placeholder="All page types" aria-label="Page type" />
                    <x-field name="q" :value="request('q')" placeholder="Search" aria-label="Search" />
                    <button class="btn-light">Filter</button>
                </form>
                <ul class="divide-y divide-navy-50">
                    @forelse ($meta as $m)
                        <li class="p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs"><span class="font-mono text-muted">{{ $m->page_type }} · {{ $m->page_key }}</span>@if ($m->is_manual)<span class="badge-amber">Locked</span>@endif</div>
                            <form method="POST" action="{{ route('app.seo.meta', $m) }}" class="mt-2 grid gap-2">
                                @csrf @method('PUT')
                                <input name="title" value="{{ $m->title }}" maxlength="60" class="input" aria-label="Title for {{ $m->page_key }}" data-count>
                                <textarea name="description" maxlength="155" rows="2" class="input" aria-label="Description for {{ $m->page_key }}" data-count>{{ $m->description }}</textarea>
                                @can('seo.update')
                                    <div class="flex items-center gap-3">
                                        <input type="hidden" name="is_manual" value="0"><x-checkbox name="is_manual" label="Lock" :checked="true" />
                                        <button class="btn-light btn-sm">Save</button>
                                    </div>
                                @endcan
                            </form>
                        </li>
                    @empty
                        <li class="p-4"><x-empty title="No meta yet" icon="globe">Click “Rebuild now” to generate meta for every page.</x-empty></li>
                    @endforelse
                </ul>
                <div class="p-4">{{ $meta->links() }}</div>
            </div>
        </div>
        <div class="space-y-6">
            <form method="POST" action="{{ route('app.seo.update') }}" class="card card-pad space-y-3">
                @csrf @method('PUT')
                <h2 class="section-title">Defaults</h2>
                <x-field name="default_title" label="Default title" :value="$settings['default_title']" maxlength="60" required />
                <x-textarea name="default_description" label="Default description" :value="$settings['default_description']" maxlength="155" rows="3" required />
                <fieldset>
                    <legend class="label">AI crawlers ({{ implode(', ', $bots) }})</legend>
                    <label class="mt-1 flex items-center gap-2 text-sm"><input type="radio" name="ai_crawlers" value="allow" @checked($settings['ai_crawlers'] === 'allow') class="text-teal-700"> Allow</label>
                    <label class="mt-1 flex items-center gap-2 text-sm"><input type="radio" name="ai_crawlers" value="block" @checked($settings['ai_crawlers'] === 'block') class="text-teal-700"> Block in robots.txt</label>
                </fieldset>
                @can('seo.update')<button class="btn-primary">Save</button>@endcan
            </form>
            <div class="card card-pad">
                <div class="flex items-center justify-between"><h2 class="section-title">robots.txt</h2><a href="{{ route('robots') }}" target="_blank" rel="noopener" class="text-sm">Open</a></div>
                <pre class="mt-2 max-h-72 overflow-auto whitespace-pre-wrap break-all rounded-lg bg-page p-3 text-xs">{{ $robots }}</pre>
                <p class="mt-2 text-sm"><a href="{{ route('sitemap') }}" target="_blank" rel="noopener">Open sitemap.xml</a></p>
            </div>
        </div>
    </div>
</x-layouts.workspace>
