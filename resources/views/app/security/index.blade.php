<x-layouts.workspace title="Security">
    <x-page-header title="Security monitoring" subtitle="Who is signed in, from which IP and device, every sign-in attempt, repeated failures and locked accounts. Passwords are never stored or shown." />
    @php($canAct = auth()->user()->hasPerm('app_security.update'))

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-stat label="Signed-in sessions" :value="$stats['sessions']" icon="users" />
        <x-stat label="Sign-ins today" :value="$stats['success']" icon="check" />
        <x-stat label="Failed today" :value="$stats['failed']" icon="alert" :tone="$stats['failed'] > 0 ? 'gold' : 'teal'" />
        <x-stat label="Locked now" :value="$stats['locked']" icon="lock" :tone="$stats['locked'] > 0 ? 'red' : 'teal'" />
        <x-stat label="IPs with 10+ failures (24 h)" :value="$stats['suspicious']" icon="shield" :tone="$stats['suspicious'] > 0 ? 'red' : 'teal'" />
    </div>

    <nav class="tabs mb-6" aria-label="Security">
        @foreach (\App\Http\Controllers\App\SecurityController::TABS as $key => $label)
            <a href="{{ route('app.security.index', ['tab' => $key]) }}" class="tab {{ $tab === $key ? 'active' : '' }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($tab === 'sessions')
        <x-filters>
            <input type="hidden" name="tab" value="sessions">
            <x-select name="portal" label="Signed in to" :options="\App\Models\LoginEvent::PORTALS" :value="request('portal')" placeholder="All" />
        </x-filters>
        <div class="card">
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>Person</th><th>Signed in to</th><th>IP address</th><th>Device</th><th>Signed in</th><th>Last active</th><th></th></tr></thead>
                <tbody>
                @forelse ($sessions as $s)
                    <tr>
                        <td><p class="font-semibold">{{ $s->name }}</p><p class="text-xs text-muted">{{ $s->email }} · {{ $s->where }}</p></td>
                        <td class="text-sm">{{ \App\Models\LoginEvent::PORTALS[$s->portal] ?? $s->portal }}</td>
                        <td class="font-mono text-sm">{{ $s->ip }}@if ($s->ipChanged)<span class="badge-amber ml-1" title="The IP changed since sign-in">changed</span>@endif
                            <a href="{{ route('app.security.index', ['tab' => 'activity', 'q' => $s->ip]) }}" class="block text-xs font-sans">History</a></td>
                        <td class="text-sm">{{ $s->device }}</td>
                        <td class="text-sm">{{ $s->since ? \App\Support\Format::datetime($s->since) : '—' }}</td>
                        <td class="text-sm">{{ $s->last->diffForHumans() }}</td>
                        <td class="text-right">
                            @if ($s->id === session()->getId())<span class="badge-teal">You</span>
                            @elseif ($canAct)<x-confirm :action="route('app.security.end', $s->id)" method="DELETE" message="End this session? {{ $s->email }} will have to sign in again." class="btn-ghost btn-sm text-red-700">End session</x-confirm>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty title="Nobody is signed in right now" icon="users" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <p class="mt-2 text-xs text-muted">Shows sessions active in the last {{ config('session.lifetime') }} minutes. Anonymous marketplace visitors are not listed.</p>

    @elseif ($tab === 'activity')
        <x-filters>
            <input type="hidden" name="tab" value="activity">
            <x-field name="q" label="Email or IP" :value="request('q')" />
            <x-select name="outcome" label="Result" :options="['problem' => 'Any failure'] + \App\Models\LoginEvent::OUTCOMES" :value="request('outcome')" placeholder="All" />
            <x-select name="portal" label="Sign-in page" :options="\App\Models\LoginEvent::PORTALS" :value="request('portal')" placeholder="All" />
            <x-field name="from" type="date" label="From" :value="request('from')" />
            <x-field name="to" type="date" label="To" :value="request('to')" />
        </x-filters>
        <div class="card">
            @can('app_security.export')<div class="flex justify-end border-b border-navy-50 p-3"><a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn-light btn-sm"><x-icon name="download" class="h-4 w-4" /> Excel</a></div>@endcan
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>When</th><th>Email</th><th>Sign-in page</th><th>Result</th><th>IP address</th><th>Device</th></tr></thead>
                <tbody>
                @forelse ($events as $e)
                    <tr>
                        <td class="whitespace-nowrap text-sm">{{ \App\Support\Format::datetime($e->created_at) }}</td>
                        <td class="text-sm">{{ $e->email ?: '—' }}@if ($e->tenantRel)<span class="block text-xs text-muted">{{ $e->tenantRel->name }}</span>@endif</td>
                        <td class="text-sm">{{ \App\Models\LoginEvent::PORTALS[$e->portal] ?? $e->portal }}</td>
                        <td><span class="{{ ['success' => 'badge-teal', 'failed' => 'badge-amber', 'locked' => 'badge-red', 'blocked' => 'badge-gray'][$e->outcome] ?? 'badge-gray' }}">{{ \App\Models\LoginEvent::OUTCOMES[$e->outcome] ?? $e->outcome }}</span>@if ($e->reason && $e->outcome !== 'success')<span class="block text-xs text-muted">{{ $e->reason }}</span>@endif</td>
                        <td class="font-mono text-sm"><a href="{{ route('app.security.index', ['tab' => 'activity', 'q' => $e->ip]) }}">{{ $e->ip }}</a></td>
                        <td class="text-sm">{{ \App\Services\LoginEvents::device($e->user_agent) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty title="No sign-in attempts match" icon="shield" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
            <div class="p-4">{{ $events->links() }}</div>
        </div>
        <p class="mt-2 text-xs text-muted">Sign-in records are kept for 180 days.</p>

    @elseif ($tab === 'ips')
        <nav class="mb-4 inline-flex rounded-full bg-white p-1 text-sm ring-1 ring-navy-50" aria-label="Period">
            @foreach ([1 => 'Last hour', 24 => 'Last 24 hours', 168 => 'Last 7 days'] as $h => $l)
                <a href="{{ route('app.security.index', ['tab' => 'ips', 'hours' => $h]) }}" class="rounded-full px-4 py-1.5 font-semibold no-underline {{ $hours === $h ? 'bg-navy text-white' : 'text-navy' }}">{{ $l }}</a>
            @endforeach
        </nav>
        <div class="grid gap-6 xl:grid-cols-2">
            <div class="card">
                <h2 class="section-title p-4">IP addresses with failed sign-ins</h2>
                <div class="table-wrap"><table class="tbl">
                    <thead><tr><th>IP address</th><th class="num">Failed</th><th class="num">Accounts tried</th><th>Last attempt</th></tr></thead>
                    <tbody>
                    @forelse ($ips as $r)
                        <tr class="{{ $r->attempts >= 10 || $r->emails >= 5 ? 'bg-red-50' : '' }}">
                            <td class="font-mono text-sm"><a href="{{ route('app.security.index', ['tab' => 'activity', 'q' => $r->ip, 'outcome' => 'problem']) }}">{{ $r->ip }}</a>@if ($r->emails >= 5)<span class="badge-red ml-1">many accounts</span>@endif</td>
                            <td class="num font-semibold">{{ $r->attempts }}</td><td class="num">{{ $r->emails }}</td>
                            <td class="text-sm">{{ \App\Support\Format::datetime(\Carbon\Carbon::parse($r->last_at)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty title="No failed sign-ins in this period" icon="check" /></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
            <div class="card">
                <h2 class="section-title p-4">Accounts with failed sign-ins</h2>
                <div class="table-wrap"><table class="tbl">
                    <thead><tr><th>Email</th><th>Sign-in page</th><th class="num">Failed</th><th class="num">From IPs</th><th>Last attempt</th></tr></thead>
                    <tbody>
                    @forelse ($emails as $r)
                        <tr>
                            <td class="text-sm"><a href="{{ route('app.security.index', ['tab' => 'activity', 'q' => $r->email, 'outcome' => 'problem']) }}">{{ $r->email ?: '—' }}</a></td>
                            <td class="text-sm">{{ \App\Models\LoginEvent::PORTALS[$r->portal] ?? $r->portal }}</td>
                            <td class="num font-semibold">{{ $r->attempts }}</td><td class="num">{{ $r->ips }}</td>
                            <td class="text-sm">{{ \App\Support\Format::datetime(\Carbon\Carbon::parse($r->last_at)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty title="No failed sign-ins in this period" icon="check" /></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
        <p class="mt-2 text-xs text-muted">Rows in red: 10 or more failures, or 5 or more different accounts tried from one IP — a sign of password guessing. Each account locks for 15 minutes after 5 wrong passwords, and sign-in pages are rate-limited per IP.</p>

    @else
        <div class="card">
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>Account</th><th>Kind</th><th>Locked until</th><th></th></tr></thead>
                <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td><p class="font-semibold">{{ $u->name }}</p><p class="text-xs text-muted">{{ $u->email }}</p></td>
                        <td class="text-sm">{{ $u->tenant ? $u->tenant->name.' · '.$u->role?->name : 'vector7 App · '.$u->role?->name }}</td>
                        <td class="text-sm">{{ \App\Support\Format::datetime($u->locked_until) }}</td>
                        <td class="text-right">@if ($canAct)<form method="POST" action="{{ route('app.security.unlock') }}">@csrf<input type="hidden" name="type" value="user"><input type="hidden" name="id" value="{{ $u->id }}"><button class="btn-light btn-sm">Unlock</button></form>@endif</td>
                    </tr>
                @endforeach
                @foreach ($customers as $c)
                    <tr>
                        <td><p class="font-semibold">{{ $c->name }}</p><p class="text-xs text-muted">{{ $c->email }}</p></td>
                        <td class="text-sm">Buyer account</td>
                        <td class="text-sm">{{ \App\Support\Format::datetime($c->locked_until) }}</td>
                        <td class="text-right">@if ($canAct)<form method="POST" action="{{ route('app.security.unlock') }}">@csrf<input type="hidden" name="type" value="customer"><input type="hidden" name="id" value="{{ $c->id }}"><button class="btn-light btn-sm">Unlock</button></form>@endif</td>
                    </tr>
                @endforeach
                @if ($users->isEmpty() && $customers->isEmpty())
                    <tr><td colspan="4"><x-empty title="No locked accounts" icon="check" /></td></tr>
                @endif
                </tbody>
            </table></div>
        </div>
    @endif
</x-layouts.workspace>
