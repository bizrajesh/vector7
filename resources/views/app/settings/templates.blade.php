<x-layouts.workspace title="Email templates">
    <x-page-header title="Email templates" subtitle="Use {placeholders} shown under each template. **text** becomes bold; links are clickable. Tenant emails carry the tenant logo and footer." :back="route('app.settings.index', ['tab' => 'smtp'])" />
    <div class="space-y-3">
        @foreach ($templates as $t)
            <details class="card">
                <summary class="flex cursor-pointer items-center justify-between gap-2 p-4"><span class="font-bold">{{ $t->name }}</span><span class="font-mono text-xs text-muted">{{ $t->key }}</span></summary>
                <form method="POST" action="{{ route('app.settings.templates.update', $t) }}" class="space-y-3 border-t border-navy-50 p-4">
                    @csrf @method('PUT')
                    <x-field name="subject" label="Subject" :value="$t->subject" id="t{{ $t->id }}s" required />
                    <x-textarea name="body" label="Body" :value="$t->body" rows="7" id="t{{ $t->id }}b" required />
                    @if ($t->placeholders)<p class="hint">Placeholders: {{ collect(explode(',', $t->placeholders))->map(fn ($p) => '{'.trim($p).'}')->implode(' ') }}</p>@endif
                    <button class="btn-primary btn-sm">Save template</button>
                </form>
            </details>
        @endforeach
    </div>
</x-layouts.workspace>
