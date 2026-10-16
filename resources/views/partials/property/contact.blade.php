{{-- Contact block (spec 17.5). Vars: $contact (array of contact_* and allow_*) --}}
<fieldset class="grid gap-4 sm:grid-cols-2">
    <legend class="section-title sm:col-span-2">Contact details</legend>
    <p class="text-sm text-muted sm:col-span-2 -mt-2">Buyers see a button for each mode you tick. The number is masked until the buyer shares their name and mobile, and every click becomes a lead.</p>
    <x-field name="contact_name" label="Contact person" :value="$contact['contact_name'] ?? ''" required />
    <x-field name="contact_mobile" label="Mobile" :value="$contact['contact_mobile'] ?? ''" required inputmode="numeric" maxlength="10" />
    <x-field name="contact_whatsapp" label="WhatsApp number" :value="$contact['contact_whatsapp'] ?? ''" inputmode="numeric" maxlength="10" hint="Blank = same as mobile." />
    <x-field name="contact_email" type="email" label="Email" :value="$contact['contact_email'] ?? ''" />
    <div class="sm:col-span-2">
        <p class="label">Preferred modes of contact</p>
        <div class="flex flex-wrap gap-4">
            <input type="hidden" name="allow_call" value="0"><x-checkbox name="allow_call" label="Call" :checked="(bool) ($contact['allow_call'] ?? true)" />
            <input type="hidden" name="allow_whatsapp" value="0"><x-checkbox name="allow_whatsapp" label="WhatsApp message" :checked="(bool) ($contact['allow_whatsapp'] ?? true)" />
            <input type="hidden" name="allow_email" value="0"><x-checkbox name="allow_email" label="Email (enquiry form)" :checked="(bool) ($contact['allow_email'] ?? true)" />
        </div>
        @error('allow_call')<p class="error">{{ $message }}</p>@enderror
    </div>
</fieldset>
