<x-layouts.workspace title="Marketing">
    <x-page-header title="Marketing" subtitle="Claude writes posts for launched projects. Connected accounts publish on schedule; others stay as drafts.">
        @can('app_settings.view')<a href="{{ route('app.settings.index', ['tab' => 'social']) }}" class="btn-light"><x-icon name="link" class="h-4 w-4" /> Connect accounts</a>@endcan
    </x-page-header>

    <section aria-labelledby="h-metrics" class="mb-8">
        <h2 id="h-metrics" class="section-title mb-3">Social media metrics</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach (\App\Services\SocialService::PLATFORMS as $key => $label)
                @php($m = $latest[$key] ?? null)
                <div class="card card-pad">
                    <div class="flex items-center justify-between">
                        <p class="flex items-center gap-2 font-bold"><x-icon :name="$key" class="h-5 w-5" /> {{ $label }}</p>
                        <span class="{{ $connected[$key] ? 'badge-teal' : 'badge-gray' }}">{{ $connected[$key] ? 'Connected' : 'Not connected' }}</span>
                    </div>
                    @if ($m)
                        <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                            <div><dt class="text-xs text-muted">Followers</dt><dd class="text-lg font-extrabold tabular-nums">{{ \App\Support\Format::num($m->followers) }}</dd></div>
                            <div><dt class="text-xs text-muted">Reach / views</dt><dd class="text-lg font-extrabold tabular-nums">{{ \App\Support\Format::num($m->reach) }}</dd></div>
                            <div><dt class="text-xs text-muted">Likes</dt><dd class="font-semibold tabular-nums">{{ \App\Support\Format::num($m->likes) }}</dd></div>
                            <div><dt class="text-xs text-muted">Comments</dt><dd class="font-semibold tabular-nums">{{ \App\Support\Format::num($m->comments) }}</dd></div>
                        </dl>
                        <p class="mt-2 text-xs text-muted">Updated @date($m->metric_date)</p>
                    @else
                        <p class="mt-3 text-sm text-muted">No metrics yet. They are pulled daily once the account is connected.</p>
                    @endif
                </div>
            @endforeach
        </div>
        @if (count($chart['labels']))
            <div class="card card-pad mt-4"><h3 class="font-bold">Followers — last 30 days</h3><x-chart id="ch-followers" :config="$chart" label="Followers per platform over the last 30 days" /></div>
        @endif
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-4 xl:col-span-2">
            <x-filters>
                <x-select name="platform" label="Platform" :options="\App\Services\SocialService::PLATFORMS" :value="request('platform')" placeholder="All" />
                <x-select name="status" label="Status" :options="['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published', 'failed' => 'Failed']" :value="request('status')" placeholder="All" />
            </x-filters>
            @forelse ($posts as $post)
                <div class="card card-pad">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="flex items-center gap-2 font-bold"><x-icon :name="$post->platform" class="h-4 w-4" /> {{ \App\Services\SocialService::PLATFORMS[$post->platform] }} · {{ $post->project?->name ?? 'vector7' }}</p>
                        <div class="flex items-center gap-2">
                            @if ($post->error === 'Not connected')<span class="badge-gray">Not connected</span>@endif
                            <span class="{{ ['published' => 'badge-teal', 'scheduled' => 'badge-blue', 'failed' => 'badge-red'][$post->status] ?? 'badge-gray' }}">{{ ucfirst($post->status) }}</span>
                        </div>
                    </div>
                    @if ($post->status === 'failed' && $post->error)<p class="mt-2 text-sm text-red-700">{{ $post->error }}</p>@endif
                    <form method="POST" action="{{ route('app.marketing.update', $post) }}" class="mt-3 space-y-2">
                        @csrf @method('PUT')
                        <textarea name="caption" rows="5" class="input" aria-label="Caption" @if ($post->status === 'published') readonly @endif>{{ $post->caption }}</textarea>
                        <input name="hashtags" value="{{ $post->hashtags }}" class="input" aria-label="Hashtags" @if ($post->status === 'published') readonly @endif>
                        <p class="text-xs text-muted">Image idea: {{ $post->image_suggestion }}</p>
                        <textarea id="mk{{ $post->id }}" class="sr-only" tabindex="-1" aria-hidden="true">{{ $post->caption }}

{{ $post->hashtags }}</textarea>
                        <div class="flex flex-wrap items-end gap-2">
                            @if ($post->status !== 'published')
                                <x-field name="scheduled_at" type="datetime-local" label="Publish at" :value="$post->scheduled_at?->format('Y-m-d\TH:i')" :id="'sch'.$post->id" />
                                @can('marketing.update')<button class="btn-light btn-sm">Save</button>@endcan
                            @else
                                <p class="text-xs text-muted">Published {{ \App\Support\Format::datetime($post->published_at) }}</p>
                            @endif
                            <button type="button" class="btn-teal btn-sm" data-copy="mk{{ $post->id }}"><x-icon name="copy" class="h-4 w-4" /> Copy</button>
                        </div>
                    </form>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @if ($post->status !== 'published' && $connected[$post->platform])
                            @can('marketing.update')<x-confirm :action="route('app.marketing.publish', $post)" message="Publish this post now?" class="btn-primary btn-sm">Publish now</x-confirm>@endcan
                        @endif
                        @can('marketing.delete')<x-confirm :action="route('app.marketing.destroy', $post)" method="DELETE" message="Delete this post?" class="btn-ghost btn-sm text-red-700">Delete</x-confirm>@endcan
                    </div>
                </div>
            @empty
                <div class="card"><x-empty title="No posts yet" icon="megaphone">Write the first one with the form on the right.</x-empty></div>
            @endforelse
            {{ $posts->links() }}
        </div>
        @can('marketing.create')
            <form method="POST" action="{{ route('app.marketing.generate') }}" class="card card-pad grid h-fit gap-3">
                @csrf
                <h2 class="section-title">Write a post with AI</h2>
                <x-select name="project_id" label="Launched project" :options="$projects" required placeholder="Choose a project" />
                <x-select name="platform" label="Platform" :options="\App\Services\SocialService::PLATFORMS" />
                <x-textarea name="brief" label="Anything to highlight?" rows="3" placeholder="Weekend site visit, bank loan tie-up…" />
                <x-field name="scheduled_at" type="datetime-local" label="Publish at (optional)" hint="Only connected accounts publish automatically." />
                <button class="btn-primary"><x-icon name="sparkles" class="h-4 w-4" /> Write post</button>
            </form>
        @endcan
    </div>
</x-layouts.workspace>
