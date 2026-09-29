<x-layouts.app title="Settings">
    <x-page-header title="Settings" subtitle="Pre-configuration used across projects, sales and shares" />
    @include('app.settings._nav')
    <form method="POST" action="{{ route('app.settings.update') }}" class="grid gap-4 lg:grid-cols-2">
        @csrf @method('PUT')
        <section class="card-pad grid gap-4 sm:grid-cols-2">
            <h2 class="section-title sm:col-span-2">Projects & sales</h2>
            <x-field name="sellable_pct" type="number" step="0.01" label="Sellable SQFT %" :value="$s['sellable_pct']" required help="Default 55%" />
            <x-field name="broker_commission_pct" type="number" step="0.01" label="Broker commission %" :value="$s['broker_commission_pct']" required help="Default 0.5%" />
            <x-field name="budget_alert_pct" type="number" label="Expense vs budget alert %" :value="$s['budget_alert_pct']" required />
            <x-field name="booking_validity_days" type="number" label="Booking validity (days)" :value="$s['booking_validity_days']" required />
            <x-field name="sale_window_working_days" type="number" label="Sale completion window (working days)" :value="$s['sale_window_working_days']" required />
            <x-field name="online_hold_hours" type="number" label="Online booking hold (hours)" :value="$s['online_hold_hours']" required help="Website bookings are held this long until Sales confirms the advance." />
        </section>
        <section class="card-pad">
            <h2 class="section-title">Buy instalments</h2>
            <p class="help mb-3">Percentages must add up to 100. Due day counts working days from the sale date (Sundays and holidays skipped).</p>
            <div class="flex flex-col gap-3">
                @foreach (array_pad(old('instalments', $s['instalments']), 3, ['pct' => '', 'due_working_day' => '']) as $i => $row)
                    <div class="grid grid-cols-[40px_1fr_1fr] items-end gap-3">
                        <span class="pb-3 text-sm font-bold text-ink-muted">#{{ $i + 1 }}</span>
                        <x-field :name="'instalments['.$i.'][pct]'" type="number" step="0.01" label="Percent" :value="$row['pct']" />
                        <x-field :name="'instalments['.$i.'][due_working_day]'" type="number" label="Due on working day" :value="$row['due_working_day']" />
                    </div>
                @endforeach
            </div>
        </section>
        <section class="card-pad grid gap-4 sm:grid-cols-2">
            <h2 class="section-title sm:col-span-2">Shares</h2>
            <x-field name="share_face_value" type="number" step="0.01" label="Face value per share (₹)" :value="$s['share_face_value']" required />
            <x-select name="share_recognition" label="Share value moves when a plot is" :options="['sold' => 'Sold (registered)', 'ror' => 'Fully paid (ROR)']" :value="$s['share_recognition']" required />
        </section>
        <section class="card-pad grid gap-4 sm:grid-cols-2">
            <h2 class="section-title sm:col-span-2">Document storage</h2>
            <x-select name="storage" label="Store documents in" :options="['local' => 'Secure server storage', 'google_drive' => 'Google Drive folder']" :value="$s['storage']" required />
            <x-field name="drive_folder_id" label="Google Drive folder ID" :value="$s['drive_folder_id']" help="Saved for the Google Drive add-on. Until it is enabled, files are stored in private server storage." />
        </section>
        <div class="lg:col-span-2"><button type="submit" class="btn-primary">Save settings</button></div>
    </form>
</x-layouts.app>
