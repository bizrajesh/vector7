<x-layouts.account title="Enquiries">
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>Date</th><th>About</th><th>Message</th><th>Status</th></tr></thead>
        <tbody>@forelse ($enquiries as $e)<tr><td class="whitespace-nowrap text-sm">@date($e->created_at)</td><td>{{ $e->project?->name ?? 'vector7 support' }}</td><td class="text-sm">{{ \Illuminate\Support\Str::limit($e->message, 120) }}</td><td><span class="badge-navy">{{ \App\Models\Enquiry::STATUSES[$e->status] }}</span></td></tr>@empty<tr><td colspan="4"><x-empty title="No enquiries" icon="chat" /></td></tr>@endforelse</tbody>
    </table></div></div>
</x-layouts.account>
