<x-layouts.workspace title="Launch & plots">
    <x-page-header title="Launch & plots" subtitle="Publish projects to the marketplace and manage plots, prices and offers.">
        @can('quick_launch.create')<a href="{{ route('ws.quick-launch.create') }}" class="btn-primary"><x-icon name="rocket" class="h-4 w-4" /> Direct launch</a>@endcan
        @can('launch.create')<a href="{{ route('ws.plot-onboarding.index') }}" class="btn-light"><x-icon name="grid" class="h-4 w-4" /> Plot onboarding</a>@endcan
        <a href="{{ route('ws.plot-onboarding.template') }}" class="btn-light"><x-icon name="download" class="h-4 w-4" /> Plot template (.xlsx)</a>
    </x-page-header>
    @can('quick_launch.create')
        <div class="card card-pad mb-8 flex flex-wrap items-center justify-between gap-4 ring-2 ring-teal-100">
            <div class="max-w-2xl">
                <p class="eyebrow">Skip the project phase</p>
                <h2 class="mt-1 text-lg font-extrabold">Launch a layout directly</h2>
                <p class="mt-1 text-sm text-muted">Approvals already done? Enter the launch details, upload the layout and photos, paste or upload the plots — and the layout goes live in one step. No Go/No-Go, estimate or stage tracking needed. You can turn it into a full project later.</p>
            </div>
            <a href="{{ route('ws.quick-launch.create') }}" class="btn-primary"><x-icon name="rocket" class="h-4 w-4" /> Direct launch</a>
        </div>
    @endcan
    <h2 class="mb-3 text-lg font-extrabold">Ready to launch</h2>
    <div class="mb-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($ready as $p)
            <div class="card card-pad">
                <p class="text-lg font-extrabold">{{ $p->name }}</p>
                <p class="text-xs text-muted">{{ $p->project_code }} · {{ $p->location }} · {{ $p->plots_count }} plots loaded</p>
                <a href="{{ route('ws.launch.show', $p) }}" class="btn-primary mt-4">{{ auth()->user()->hasPerm('launch.approve') ? 'Launch' : 'View' }}</a>
            </div>
        @empty
            <div class="card md:col-span-2 xl:col-span-3"><x-empty title="No projects are ready to launch" icon="rocket">Projects appear here once their approval stages are complete — or use Direct launch / Skip to launch.</x-empty></div>
        @endforelse
    </div>
    @if ($inPhase->isNotEmpty())
        <h2 class="mb-1 text-lg font-extrabold">Still in the project phase</h2>
        <p class="mb-3 text-sm text-muted">Need to launch one of these now? <strong>Skip to launch</strong> moves it straight to Ready to Launch — then add the layout and plots and go live. Its stages and estimate stay as they are.</p>
        <div class="mb-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($inPhase as $p)
                <div class="card card-pad">
                    <div class="flex items-start justify-between gap-2"><p class="text-lg font-extrabold">{{ $p->name }}</p><x-project-status :status="$p->status" /></div>
                    <p class="text-xs text-muted">{{ $p->project_code }} · {{ $p->location }}</p>
                    @can('launch.approve')
                        <details class="mt-3">
                            <summary class="btn-light btn-sm cursor-pointer list-none">Skip to launch</summary>
                            <form method="POST" action="{{ route('ws.launch.skip', $p) }}" class="mt-3 space-y-3">
                                @csrf
                                <x-select name="approval_type" label="Approval" :options="array_combine(\App\Models\Project::APPROVAL_TYPES, \App\Models\Project::APPROVAL_TYPES)" :value="$p->approval_type" id="ap_t{{ $p->id }}" />
                                <div class="grid grid-cols-2 gap-3">
                                    <x-field name="approval_authority" label="Authority" placeholder="DTCP / CMDA / Panchayat" :value="$p->approval_authority" id="ap_a{{ $p->id }}" />
                                    <x-field name="approval_number" label="Approval no." :value="$p->approval_number" id="ap_n{{ $p->id }}" />
                                </div>
                                <x-field name="reason" label="Note (optional)" placeholder="e.g. Approvals received outside vector7" id="ap_r{{ $p->id }}" />
                                <button class="btn-primary btn-sm w-full">Move to Ready to Launch</button>
                            </form>
                        </details>
                    @endcan
                </div>
            @endforeach
        </div>
    @endif
    <h2 class="mb-3 text-lg font-extrabold">Launched</h2>
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>Project</th><th>Launched</th><th class="num">Plots</th><th class="num">Available</th><th class="num">Sold</th><th></th></tr></thead>
        <tbody>
        @forelse ($launched as $p)
            <tr><td><p class="font-semibold">{{ $p->name }}</p><p class="text-xs text-muted">{{ $p->project_code }} · {{ $p->location }}</p></td><td class="text-sm">@date($p->launched_at)</td>
                <td class="num">{{ $p->plots_count }}</td><td class="num">{{ $p->available_count }}</td><td class="num">{{ $p->sold_count }}</td>
                <td class="text-right whitespace-nowrap"><a href="{{ $p->publicUrl() }}" class="btn-ghost btn-sm" target="_blank" rel="noopener">Public page</a><a href="{{ route('ws.launch.show', $p) }}" class="btn-light btn-sm">Plots</a></td></tr>
        @empty
            <tr><td colspan="6" class="text-muted">Nothing launched yet.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</x-layouts.workspace>
