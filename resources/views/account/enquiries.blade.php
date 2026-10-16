<x-layouts.account title="Enquiries">
    <div class="card">
        <h2 class="section-title p-4">Properties you contacted</h2>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th>Since</th><th>Promoter</th><th>About</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($leads as $l)
                <tr>
                    <td class="whitespace-nowrap text-sm">@date($l->created_at)</td>
                    <td>{{ $l->tenant?->name }}</td>
                    <td class="text-sm">{{ $l->interests->map->label()->implode(', ') ?: '—' }}</td>
                    <td><span class="badge-navy">{{ $l->stage?->is_won ? 'Booked / bought' : ($l->stage?->is_lost ? 'Closed' : ($l->stage?->key === 'new' ? 'Sent' : 'In touch')) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty title="No enquiries yet" icon="chat">When you call, message or enquire about a property while signed in, it appears here.</x-empty></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    @if ($enquiries->isNotEmpty())
        <div class="card mt-6">
            <h2 class="section-title p-4">Messages to vector7 support</h2>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>Date</th><th>Message</th><th>Status</th></tr></thead>
                <tbody>@foreach ($enquiries as $e)<tr><td class="whitespace-nowrap text-sm">@date($e->created_at)</td><td class="text-sm">{{ \Illuminate\Support\Str::limit($e->message, 120) }}</td><td><span class="badge-navy">{{ \App\Models\Enquiry::STATUSES[$e->status] }}</span></td></tr>@endforeach</tbody>
            </table></div>
        </div>
    @endif
</x-layouts.account>
