{{-- Payment amount + mode segmented control + reference + date. $amountLabel optional. --}}
<x-field name="amount" type="number" step="0.01" :label="$amountLabel ?? 'Amount (₹)'" inputmode="decimal" :required="$required ?? true" class="[&_input]:text-[17px] [&_input]:font-bold" />
<fieldset>
    <legend class="label">Payment mode</legend>
    <div class="grid grid-cols-4 gap-2">
        @foreach (['upi' => 'UPI', 'cash' => 'Cash', 'neft' => 'NEFT', 'cheque' => 'Cheque'] as $value => $label)
            <label><input type="radio" name="mode" value="{{ $value }}" class="peer sr-only" @checked(old('mode', 'upi') === $value)>
                <span class="flex h-11 cursor-pointer items-center justify-center rounded-[10px] border border-[#D6D2BD] bg-white text-[13.5px] font-semibold text-ink-2 peer-checked:border-teal peer-checked:bg-teal peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-sage">{{ $label }}</span></label>
        @endforeach
    </div>
    @error('mode')<p class="error">{{ $message }}</p>@enderror
</fieldset>
<div class="grid grid-cols-2 gap-3">
    <x-field name="reference_no" label="UTR / cheque no" help="Not needed for cash" />
    <x-field name="paid_at" type="date" label="Paid on" :value="now()->toDateString()" />
</div>
