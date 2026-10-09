<x-layouts.workspace title="Settings">
    <x-page-header title="Tenant settings" :subtitle="$tenant->name.' · Tenant ID '.$tenant->code">
        <a href="{{ route('ws.settings.plan') }}" class="btn-light"><x-icon name="chart" class="h-4 w-4" /> My plan & usage</a>
        @can('masters.export')<a href="{{ route('ws.template.export') }}" class="btn-light"><x-icon name="download" class="h-4 w-4" /> Export setup (template)</a>@endcan
    </x-page-header>
    @php($tabs = ['organisation' => 'Organisation', 'sales' => 'Booking, sales & refunds', 'disclaimers' => 'Disclaimers', 'ids' => 'ID seeds', 'calendar' => 'Working days'])
    <nav class="tabs mb-6">@foreach ($tabs as $k => $l)<a href="{{ route('ws.settings.index', ['tab' => $k]) }}" class="tab {{ $tab === $k ? 'active' : '' }}">{{ $l }}</a>@endforeach</nav>
    @php($ro = ! auth()->user()->hasPerm('tenant_settings.update'))

    @if ($tab === 'organisation')
        <form method="POST" action="{{ route('ws.settings.organisation') }}" enctype="multipart/form-data" class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @csrf @method('PUT')
            <div class="flex items-center gap-4 sm:col-span-2 lg:col-span-3">
                @if ($tenant->logo_path)<img src="{{ $tenant->logoUrl() }}" alt="{{ $tenant->name }} logo" class="h-16 w-16 rounded-xl object-contain ring-1 ring-navy-50" width="64" height="64">@endif
                <x-field name="logo" type="file" label="Logo / profile picture" accept="image/png,image/jpeg" hint="PNG or JPG, square, at least 512×512 px, max 2 MB" />
            </div>
            <x-field name="name" label="Name or company name" :value="$tenant->name" required class="sm:col-span-2" />
            <x-field name="support_email" type="email" label="Support email" :value="$tenant->support_email" />
            <x-field name="address_line1" label="Address line 1" :value="$tenant->address_line1" required class="sm:col-span-2" />
            <x-field name="address_line2" label="Address line 2" :value="$tenant->address_line2" />
            <x-field name="village" label="Village" :value="$tenant->village" />
            <x-field name="city" label="City / town" :value="$tenant->city" required />
            <x-field name="district" label="District" :value="$tenant->district" required />
            <x-select name="state" label="State" :options="array_combine(\App\Services\TemplateImporter::STATES, \App\Services\TemplateImporter::STATES)" :value="$tenant->state" required />
            <x-field name="pin" label="PIN code" :value="$tenant->pin" required inputmode="numeric" maxlength="6" />
            <x-field name="contact" label="Contact number" :value="$tenant->contact" required inputmode="numeric" maxlength="10" />
            <x-field name="alt_contact" label="Alternate contact" :value="$tenant->alt_contact" inputmode="numeric" maxlength="12" />
            <x-field name="website" type="url" label="Website" :value="$tenant->website" placeholder="https://" />
            <x-field name="instagram" type="url" label="Instagram" :value="$tenant->instagram" placeholder="https://www.instagram.com/…" />
            <x-field name="facebook" type="url" label="Facebook" :value="$tenant->facebook" placeholder="https://www.facebook.com/…" />
            <x-field name="youtube" type="url" label="YouTube" :value="$tenant->youtube" placeholder="https://www.youtube.com/@…" />
            <x-field name="linkedin" type="url" label="LinkedIn" :value="$tenant->linkedin" placeholder="https://www.linkedin.com/company/…" />
            @unless ($ro)<div class="sm:col-span-2 lg:col-span-3"><button class="btn-primary">Save organisation</button></div>@endunless
        </form>
    @elseif ($tab === 'sales')
        <form method="POST" action="{{ route('ws.settings.sales') }}" class="space-y-6">
            @csrf @method('PUT')
            <div class="card card-pad grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <h2 class="section-title sm:col-span-2 lg:col-span-4">Booking & sale windows</h2>
                <x-field name="booking_validity_days" type="number" label="Booking validity (working days)" :value="$settings->booking_validity_days" min="1" max="90" required />
                <x-field name="sale_window_days" type="number" label="Sale completion window (working days)" :value="$settings->sale_window_days" min="1" max="365" required />
                <x-select name="currency" label="Currency" :options="['INR' => 'INR — Indian Rupee']" :value="$settings->currency" />
                <x-field name="sellable_pct" type="number" step="0.01" label="Sellable sq ft %" :value="$settings->sellable_pct" required />
                <x-field name="broker_commission_pct" type="number" step="0.01" label="Broker commission %" :value="$settings->broker_commission_pct" required />
                <x-field name="budget_alert_pct" type="number" step="0.01" label="Expense vs budget alert %" :value="$settings->budget_alert_pct" required />
                <x-field name="mrp_multiplier" type="number" step="0.01" label="MRP multiplier (×)" :value="$settings->mrp_multiplier" required hint="MRP per sq ft = production cost per sq ft × this" />
            </div>
            <div class="card card-pad" data-repeater>
                <h2 class="section-title">Instalment plan</h2>
                <p class="text-sm text-muted">Must total 100%. The last due day must be within the sale completion window. Due days count working days from the sale date.</p>
                <div class="table-wrap mt-3"><table class="tbl">
                    <thead><tr><th>Name</th><th>%</th><th>Due (working days)</th><th></th></tr></thead>
                    <tbody data-repeater-body>
                    @foreach (old('inst', $instalments->map(fn ($i) => ['name' => $i->name, 'percent' => (float) $i->percent, 'due_working_days' => $i->due_working_days])->all()) as $k => $i)
                        <tr data-repeater-row>
                            <td><input class="input" name="inst[{{ $k }}][name]" value="{{ $i['name'] }}" required aria-label="Instalment name"></td>
                            <td><input class="input w-24" type="number" step="0.01" name="inst[{{ $k }}][percent]" value="{{ $i['percent'] }}" required aria-label="Percent"></td>
                            <td><input class="input w-28" type="number" name="inst[{{ $k }}][due_working_days]" value="{{ $i['due_working_days'] }}" required aria-label="Due working days"></td>
                            <td><button type="button" class="btn-ghost btn-sm" data-repeater-remove aria-label="Remove row"><x-icon name="trash" class="h-4 w-4" /></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <template><tr data-repeater-row>
                    <td><input class="input" name="inst[__i__][name]" required aria-label="Instalment name"></td>
                    <td><input class="input w-24" type="number" step="0.01" name="inst[__i__][percent]" required aria-label="Percent"></td>
                    <td><input class="input w-28" type="number" name="inst[__i__][due_working_days]" required aria-label="Due working days"></td>
                    <td><button type="button" class="btn-ghost btn-sm" data-repeater-remove aria-label="Remove row">✕</button></td>
                </tr></template>
                <button type="button" class="btn-light btn-sm mt-3" data-repeater-add><x-icon name="plus" class="h-4 w-4" /> Add instalment</button>
            </div>
            <div class="card card-pad" data-repeater>
                <h2 class="section-title">Refund penalties</h2>
                <p class="text-sm text-muted">Used when a customer misses the sale completion window and asks for a refund. Days are counted after the window ends.</p>
                <div class="table-wrap mt-3"><table class="tbl">
                    <thead><tr><th>From day</th><th>To day (blank = no end)</th><th>Penalty type</th><th>Value</th><th></th></tr></thead>
                    <tbody data-repeater-body>
                    @foreach (old('pen', $penalties->map(fn ($p) => ['from_days' => $p->from_days, 'to_days' => $p->to_days, 'penalty_type' => $p->penalty_type, 'value' => (float) $p->value])->all()) as $k => $p)
                        <tr data-repeater-row>
                            <td><input class="input w-24" type="number" name="pen[{{ $k }}][from_days]" value="{{ $p['from_days'] }}" required aria-label="From day"></td>
                            <td><input class="input w-24" type="number" name="pen[{{ $k }}][to_days]" value="{{ $p['to_days'] }}" aria-label="To day"></td>
                            <td><select class="input" name="pen[{{ $k }}][penalty_type]" aria-label="Penalty type"><option value="percent" @selected($p['penalty_type'] === 'percent')>% of paid amount</option><option value="fixed" @selected($p['penalty_type'] === 'fixed')>Fixed amount (₹)</option></select></td>
                            <td><input class="input w-32" type="number" step="0.01" name="pen[{{ $k }}][value]" value="{{ $p['value'] }}" required aria-label="Value"></td>
                            <td><button type="button" class="btn-ghost btn-sm" data-repeater-remove aria-label="Remove row"><x-icon name="trash" class="h-4 w-4" /></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <template><tr data-repeater-row>
                    <td><input class="input w-24" type="number" name="pen[__i__][from_days]" required aria-label="From day"></td>
                    <td><input class="input w-24" type="number" name="pen[__i__][to_days]" aria-label="To day"></td>
                    <td><select class="input" name="pen[__i__][penalty_type]" aria-label="Penalty type"><option value="percent">% of paid amount</option><option value="fixed">Fixed amount (₹)</option></select></td>
                    <td><input class="input w-32" type="number" step="0.01" name="pen[__i__][value]" required aria-label="Value"></td>
                    <td><button type="button" class="btn-ghost btn-sm" data-repeater-remove aria-label="Remove row">✕</button></td>
                </tr></template>
                <button type="button" class="btn-light btn-sm mt-3" data-repeater-add><x-icon name="plus" class="h-4 w-4" /> Add penalty row</button>
            </div>
            @unless ($ro)<button class="btn-primary">Save sales rules</button>@endunless
        </form>
    @elseif ($tab === 'disclaimers')
        <form method="POST" action="{{ route('ws.settings.disclaimers') }}" class="card card-pad space-y-4">
            @csrf @method('PUT')
            <p class="text-sm text-muted">Shown and accepted (checkbox, time and IP stored) at each step, and printed on receipts.</p>
            <x-textarea name="disclaimer_booking" label="Booking disclaimer" :value="$settings->disclaimer_booking" rows="4" required />
            <x-textarea name="disclaimer_sale" label="Sale disclaimer" :value="$settings->disclaimer_sale" rows="4" required />
            <x-textarea name="disclaimer_registration" label="Registration disclaimer" :value="$settings->disclaimer_registration" rows="4" required />
            <x-textarea name="disclaimer_refund" label="Refund disclaimer" :value="$settings->disclaimer_refund" rows="4" required />
            @unless ($ro)<button class="btn-primary">Save disclaimers</button>@endunless
        </form>
    @elseif ($tab === 'ids')
        <form method="POST" action="{{ route('ws.settings.ids') }}" class="card card-pad">
            @csrf @method('PUT')
            <p class="mb-4 text-sm text-muted">You set the prefix; the rest is added automatically from the date and time. If two IDs clash, a suffix -2, -3 … is added.</p>
            <div class="table-wrap"><table class="tbl">
                <thead><tr><th>ID</th><th>Prefix</th><th>Format</th><th>Next example</th></tr></thead>
                <tbody>
                @foreach (\App\Services\IdGenerator::TYPES as $type => $cfg)
                    @php($pre = old("prefix.$type", $sequences[$type] ?? $cfg['default']))
                    <tr>
                        <td class="font-semibold">{{ $cfg['label'] }}</td>
                        <td><input class="input w-28 uppercase" name="prefix[{{ $type }}]" value="{{ $pre }}" maxlength="6" required aria-label="{{ $cfg['label'] }} prefix">@error("prefix.$type")<p class="error">{{ $message }}</p>@enderror</td>
                        <td class="text-sm text-muted">prefix + {{ $cfg['format'] === 'dmYi' ? 'DDMMYYYYmm' : 'DDMMYYYYmmss' }}</td>
                        <td class="font-mono text-sm">{{ $pre.now()->format($cfg['format']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            @unless ($ro)<button class="btn-primary mt-4">Save prefixes</button>@endunless
        </form>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="card card-pad lg:col-span-2">
                <h2 class="section-title">Holidays</h2>
                <p class="text-sm text-muted">Saturday and Sunday are always non-working days. Holidays you add here are skipped too when counting booking validity, instalment due dates and project schedules.</p>
                <div class="table-wrap mt-3"><table class="tbl">
                    <thead><tr><th>Date</th><th>Day</th><th>Holiday</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($holidays as $h)
                        <tr><td>{{ $h->date->format('d-m-Y') }}</td><td>{{ $h->date->format('l') }}</td><td>{{ $h->name }}</td>
                            <td class="text-right">@unless ($ro)<x-confirm :action="route('ws.settings.holidays.destroy', $h)" method="DELETE" message="Remove {{ $h->name }}?" class="btn-ghost btn-sm">Remove</x-confirm>@endunless</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">No holidays added.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
            @unless ($ro)
                <form method="POST" action="{{ route('ws.settings.holidays.store') }}" class="card card-pad space-y-3">
                    @csrf
                    <h2 class="section-title">Add holiday</h2>
                    <x-field name="date" type="date" label="Date" required />
                    <x-field name="name" label="Name" required placeholder="Pongal" />
                    <button class="btn-primary">Add</button>
                </form>
            @endunless
        </div>
    @endif
</x-layouts.workspace>
