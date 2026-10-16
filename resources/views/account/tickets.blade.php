<x-layouts.account :title="__('Help & tickets')">
    <div class="grid gap-6 lg:grid-cols-5">
        <div class="card lg:col-span-3"><div class="table-wrap"><table class="tbl">
            <thead><tr><th>{{ __('Ticket') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Status') }}</th><th>{{ __('Updated') }}</th></tr></thead>
            <tbody>@forelse ($tickets as $t)<tr><td class="font-mono text-xs"><a href="{{ route('account.tickets.show', $t->id) }}">{{ $t->number }}</a></td><td>{{ $t->subject }}</td><td><span class="badge-navy">{{ __(\App\Models\Ticket::STATUSES[$t->status]) }}</span></td><td class="text-xs">@date($t->updated_at)</td></tr>@empty<tr><td colspan="4"><x-empty :title="__('No tickets')" icon="lifebuoy" /></td></tr>@endforelse</tbody>
        </table></div></div>
        <form method="POST" action="{{ route('account.tickets.store') }}" enctype="multipart/form-data" class="card card-pad space-y-3 lg:col-span-2">
            @csrf
            <h2 class="section-title">{{ __('New ticket') }}</h2>
            <x-select name="category" :label="__('Topic')" :options="collect(\App\Models\Ticket::CATEGORIES)->except(['billing'])->map(fn ($l) => __($l))->all()" />
            <x-field name="subject" :label="__('Subject')" required />
            <x-textarea name="body" :label="__('Details')" rows="5" required />
            <x-field name="file" type="file" :label="__('Attachment (optional)')" accept=".pdf,.jpg,.jpeg,.png,.webp" />
            <button class="btn-primary">{{ __('Open ticket') }}</button>
        </form>
    </div>
</x-layouts.account>
