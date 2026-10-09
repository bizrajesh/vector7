@if ($g = session('generated'))
    <div class="card card-pad mb-6 ring-2 ring-teal" role="status">
        <div class="flex items-start gap-3">
            <div class="rounded-xl bg-teal-50 p-2 text-teal-700"><x-icon name="key" /></div>
            <div class="min-w-0 flex-1">
                <p class="font-bold">New password for {{ $g['name'] }} ({{ $g['email'] }})</p>
                <p class="mt-1 text-sm text-muted">Shown only once. Copy it now — it is stored only as a hash.
                    {{ $g['must_change'] ? 'The user must set a new password after signing in.' : '' }}
                    {{ $g['emailed'] ? 'It was also emailed to the user.' : '' }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <code id="gen-pw" class="rounded-lg bg-navy px-4 py-2 font-mono text-lg tracking-wider text-white">{{ $g['password'] }}</code>
                    <button type="button" class="btn-teal btn-sm" data-copy="gen-pw"><x-icon name="copy" class="h-4 w-4" /> Copy</button>
                </div>
            </div>
        </div>
    </div>
@endif
