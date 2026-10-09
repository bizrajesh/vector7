<x-layouts.workspace title="Launch & plots">
    <x-page-header title="Launch & plots" subtitle="Publish Ready to Launch projects to the marketplace and manage plots, prices and offers.">
        <a href="{{ route('ws.launch.sample') }}" class="btn-light"><x-icon name="download" class="h-4 w-4" /> Sample plot CSV</a>
    </x-page-header>
    <h2 class="mb-3 text-lg font-extrabold">Ready to launch</h2>
    <div class="mb-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($ready as $p)
            <div class="card card-pad">
                <p class="text-lg font-extrabold">{{ $p->name }}</p>
                <p class="text-xs text-muted">{{ $p->project_code }} · {{ $p->location }} · {{ $p->plots_count }} plots loaded</p>
                <a href="{{ route('ws.launch.show', $p) }}" class="btn-primary mt-4">{{ auth()->user()->hasPerm('launch.approve') ? 'Launch' : 'View' }}</a>
            </div>
        @empty
            <div class="card md:col-span-2 xl:col-span-3"><x-empty title="No projects are ready to launch" icon="rocket">Projects appear here once their approval stages are complete.</x-empty></div>
        @endforelse
    </div>
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
