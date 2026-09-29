<div class="grid gap-4 lg:grid-cols-3">
    <section class="card lg:col-span-2 overflow-x-auto">
        <table class="table min-w-[520px]">
            <thead><tr><th class="pl-4">Facility</th><th>Qty</th><th>Unit cost</th><th>Total</th><th>Deducted area</th><th></th></tr></thead>
            <tbody>
            @forelse ($layout->facilities as $row)
                <tr>
                    <td class="pl-4 font-semibold">{{ $row->facility->name }}</td>
                    <td class="num">{{ (float) $row->qty }} {{ $row->facility->unit }}</td>
                    <td class="num">@inr($row->unit_cost)</td>
                    <td class="num font-semibold">@inr($row->total)</td>
                    <td class="num">{{ (float) $row->area_sqft ? number_format((float) $row->area_sqft).' ft²' : '—' }}</td>
                    <td class="text-right">@if ($editable && $canManage)<form method="POST" action="{{ route('app.layouts.facilities.destroy', [$layout, $row]) }}">@csrf @method('DELETE')<button class="rounded-lg p-2 text-ink-muted hover:text-red-700" aria-label="Remove facility"><x-icon name="trash" class="h-4 w-4" /></button></form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="pl-4 text-ink-muted">No facilities selected.</td></tr>
            @endforelse
            </tbody>
            <tfoot><tr><td class="pl-4 pt-3 font-bold" colspan="3">Facilities total</td><td class="num pt-3 font-bold">@inr($layout->facilities->sum('total'))</td><td colspan="2"></td></tr></tfoot>
        </table>
    </section>
    @if ($editable && $canManage)
        <form method="POST" action="{{ route('app.layouts.facilities.store', $layout) }}" class="card-pad flex flex-col gap-3">@csrf
            <h2 class="section-title">Add or update facility</h2>
            <x-select name="facility_id" label="Facility" :options="$facilities->mapWithKeys(fn ($f) => [$f->id => $f->name.' ('.$f->unit.')'])" required />
            <x-field name="qty" type="number" step="0.01" label="Quantity" required help="For area facilities (park, road) enter square feet; it is deducted from sellable area." />
            <x-field name="unit_cost" type="number" step="0.01" label="Unit cost (₹)" help="Leave blank to use the default from Settings." />
            <button class="btn-primary">Save</button>
        </form>
    @endif
</div>
