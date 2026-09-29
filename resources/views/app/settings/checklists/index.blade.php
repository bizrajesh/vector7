<x-layouts.app title="Checklists">
    <x-page-header title="Settings" subtitle="Checklist templates (the Registration template is used for every registration)" />
    @include('app.settings._nav')
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            @foreach ($templates as $template)
                <a href="{{ route('app.settings.checklists.show', $template) }}" class="flex items-center gap-3 border-t border-line-soft px-4 py-3.5 text-ink no-underline first:border-t-0 hover:bg-cream-100 hover:text-ink">
                    <span class="flex-1 font-semibold">{{ $template->name }}</span>
                    <span class="{{ $template->purpose === 'registration' ? 'badge-ror' : 'badge-rs' }}">{{ ucfirst($template->purpose) }}</span>
                    <span class="text-sm text-ink-muted">{{ $template->items_count }} items</span>
                </a>
            @endforeach
        </div>
        <form method="POST" action="{{ route('app.settings.checklists.store') }}" class="card-pad flex flex-col gap-3">
            @csrf
            <h2 class="section-title">New checklist</h2>
            <x-field name="name" label="Name" required />
            <x-select name="purpose" label="Purpose" :options="['general' => 'General', 'registration' => 'Registration']" />
            <button type="submit" class="btn-primary">Create</button>
        </form>
    </div>
</x-layouts.app>
