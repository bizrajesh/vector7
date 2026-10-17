<x-layouts.workspace title="Support desk">
    <x-page-header :title="'Hello, '.\Illuminate\Support\Str::before(auth()->user()->name, ' ')" subtitle="Your support queue: tickets, enquiries, service requests, chatbot conversations and sign-in security." />
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($tiles as [$label, $count, $url, $icon])
            <a href="{{ $url }}" class="card card-pad flex items-start gap-4 text-navy no-underline transition hover:shadow-lift">
                <div class="rounded-xl p-2.5 {{ $count > 0 ? 'bg-amber-100 text-amber-800' : 'bg-teal-50 text-teal-700' }}">{!! \App\Support\Icons::svg($icon, 'h-6 w-6') !!}</div>
                <div><p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ $label }}</p><p class="mt-1 text-2xl font-extrabold">{{ \App\Support\Format::num($count) }}</p></div>
            </a>
        @endforeach
    </div>
    @if ($mine->isNotEmpty())
        <div class="card mt-6">
            <h2 class="section-title p-4">Assigned to me</h2>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>Ticket</th><th>Subject</th><th>Priority</th><th>SLA due</th></tr></thead>
                <tbody>
                @foreach ($mine as $t)
                    <tr class="{{ $t->sla_due_at && $t->sla_due_at->isPast() ? 'bg-red-50' : '' }}">
                        <td class="font-mono text-sm"><a href="{{ route('app.tickets.show', $t) }}">{{ $t->number }}</a></td>
                        <td>{{ $t->subject }}</td>
                        <td class="text-sm">{{ \App\Models\Ticket::PRIORITIES[$t->priority] ?? $t->priority }}</td>
                        <td class="text-sm">{{ $t->sla_due_at ? \App\Support\Format::datetime($t->sla_due_at) : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    @endif
</x-layouts.workspace>
