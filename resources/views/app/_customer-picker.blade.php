{{-- Pick an existing customer or enter a new one. Expects $customers. --}}
<fieldset class="flex flex-col gap-3">
    <legend class="section-title mb-1">Customer</legend>
    <div class="flex rounded-xl bg-[#E9E6D6] p-1" role="radiogroup">
        <label class="flex-1"><input type="radio" name="customer_mode" value="new" class="peer sr-only" @checked(old('customer_mode', 'new') === 'new')><span class="flex h-10 cursor-pointer items-center justify-center rounded-[9px] text-sm font-semibold text-ink-2 peer-checked:bg-white peer-checked:text-ink peer-checked:shadow-sm">New customer</span></label>
        <label class="flex-1"><input type="radio" name="customer_mode" value="existing" class="peer sr-only" @checked(old('customer_mode') === 'existing')><span class="flex h-10 cursor-pointer items-center justify-center rounded-[9px] text-sm font-semibold text-ink-2 peer-checked:bg-white peer-checked:text-ink peer-checked:shadow-sm">Existing</span></label>
    </div>
    <div data-show-when="customer_mode=existing">
        <x-select name="customer_id" label="Customer" :options="$customers->mapWithKeys(fn ($c) => [$c->id => $c->name.' · '.$c->phone])" placeholder="Choose customer" />
    </div>
    <div data-show-when="customer_mode=new" class="grid gap-3 sm:grid-cols-2">
        <x-field name="customer[name]" label="Full name" autocomplete="name" class="sm:col-span-2" />
        <x-field name="customer[phone]" type="tel" label="Mobile" inputmode="tel" autocomplete="tel" />
        <x-field name="customer[email]" type="email" label="Email" autocomplete="email" />
        <x-field name="customer[aadhaar]" label="Aadhaar" inputmode="numeric" autocomplete="off" help="Stored encrypted; shown masked" />
        <x-field name="customer[pan]" label="PAN" autocapitalize="characters" autocomplete="off" />
        <x-field name="customer[address]" label="Address" class="sm:col-span-2" autocomplete="street-address" />
    </div>
</fieldset>
