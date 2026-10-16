<x-layouts.account :title="__('Enquiries')">
    <div class="card">
        <h2 class="section-title p-4">{{ __('Properties you contacted') }}</h2>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th>{{ __('Since') }}</th><th>{{ __('Promoter') }}</th><th>{{ __('About') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($leads as $l)
                <tr>
                    <td class="whitespace-nowrap text-sm">@date($l->created_at)</td>
                    <td>{{ $l->tenant?->name }}</td>
                    <td class="text-sm">{{ $l->interests->map->label()->implode(', ') ?: '—' }}</td>
                    <td><span class="badge-navy">{{ $l->stage?->is_won ? __('Booked / bought') : ($l->stage?->is_lost ? __('Closed') : ($l->stage?->key === 'new' ? __('Sent') : __('In touch'))) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty :title="__('No enquiries yet')" icon="chat">{{ __('When you call, message or enquire about a property while signed in, it appears here.') }}</x-empty></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    @if ($unlocks->isNotEmpty())
        <div class="card mt-6">
            <h2 class="section-title p-4">{{ __('Contacts you unlocked') }}</h2>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Property') }}</th><th class="num">{{ __('Paid') }}</th><th>{{ __('Valid until') }}</th></tr></thead>
                <tbody>
                @foreach ($unlocks as $u)
                    @php($prop = $u->listing_id ? $u->listing : $u->project)
                    <tr>
                        <td class="font-mono text-sm">{{ $u->number }}<span class="block text-xs text-muted">@date($u->paid_at)</span></td>
                        <td>@if ($prop)<a href="{{ $prop->publicUrl() }}#contact" class="font-semibold text-teal-700 hover:underline">{{ $u->title }}</a>@else{{ $u->title }}@endif</td>
                        <td class="num">@inr($u->total)</td>
                        <td class="text-sm">{{ $u->expires_at ? ($u->expires_at->isPast() ? __('Expired') : $u->expires_at->format('d-m-Y')) : __('No end') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    @endif
    @if ($enquiries->isNotEmpty())
        <div class="card mt-6">
            <h2 class="section-title p-4">{{ __('Messages to vector7 support') }}</h2>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Message') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>@foreach ($enquiries as $e)<tr><td class="whitespace-nowrap text-sm">@date($e->created_at)</td><td class="text-sm">{{ \Illuminate\Support\Str::limit($e->message, 120) }}</td><td><span class="badge-navy">{{ __(\App\Models\Enquiry::STATUSES[$e->status]) }}</span></td></tr>@endforeach</tbody>
            </table></div>
        </div>
    @endif
</x-layouts.account>
