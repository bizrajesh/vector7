{{-- Public contact block (spec 17.5, Plans v2). Vars: $kind ('listing'|'project'), $owner, $ownerId, $c (['name','mobile','whatsapp','email','modes']), $title, $src --}}
@php
    $revealed = session('revealed')[$kind.':'.$ownerId] ?? null;
    $customer = auth('customer')->user();
    $person = session('contact_person') ?? ($customer?->mobile ? ['name' => $customer->name, 'mobile' => $customer->mobile] : null);
    $modes = $c['modes'];
    $formId = 'cf_'.$kind.$ownerId;
    $hasPhone = in_array('call', $modes, true) || in_array('whatsapp', $modes, true);
    $fee = isset($owner) ? \App\Services\ContactCharges::due($customer, $kind, $owner) : null;
    $unlock = isset($owner) && ! $fee && \App\Services\ContactCharges::amount($kind, $owner) ? \App\Services\ContactCharges::unlocked($customer, $kind, $ownerId) : null;
    $cbOpen = $errors->hasAny(['preferred_mode', 'preferred_slot', 'preferred_date', 'message', 'email']) || ! $hasPhone || $fee;
@endphp
<section class="card card-pad scroll-mt-24" id="contact" aria-labelledby="contact-h" data-contact data-url="{{ route('market.contact.reveal', [$kind, $ownerId]) }}" data-known="{{ $person ? '1' : '0' }}">
    <h2 id="contact-h" class="font-extrabold">{{ $kind === 'project' ? __('Contact the promoter') : __('Contact the owner') }}</h2>
    <p class="mt-1 text-sm"><strong>{{ $c['name'] }}</strong></p>
    @if ($hasPhone && $fee)
        {{-- Paid contact view: one small payment (to vector7) unlocks this property's number --}}
        <p class="mt-1 text-lg font-extrabold tracking-wide">{{ \App\Models\ListingContact::mask($c['mobile']) }}</p>
        <div class="mt-3 rounded-xl bg-page p-3">
            <p class="text-sm font-semibold">{{ __('See the full number for ₹:amount', ['amount' => \App\Support\Format::num($fee)]) }}</p>
            <p class="mt-1 text-xs text-muted">{{ trans_choice('{0} One payment unlocks this property\'s contact. Paid securely online to vector7.|[1,*] One payment unlocks this property\'s contact for :days days. Paid securely online to vector7.', \App\Services\ContactCharges::validityDays(), ['days' => \App\Services\ContactCharges::validityDays()]) }}</p>
            @if ($customer)
                <form method="POST" action="{{ route('market.contact.unlock', [$kind, $ownerId]) }}" class="mt-3">
                    @csrf
                    <button class="btn-primary w-full"><x-icon name="phone" class="h-4 w-4" /> {{ __('Pay ₹:amount & view number', ['amount' => \App\Support\Format::num($fee)]) }}</button>
                </form>
            @else
                <a href="{{ route('market.contact.unlock.start', [$kind, $ownerId]) }}" class="btn-primary mt-3 w-full" rel="nofollow"><x-icon name="phone" class="h-4 w-4" /> {{ __('Sign in & pay ₹:amount', ['amount' => \App\Support\Format::num($fee)]) }}</a>
            @endif
        </div>
    @elseif ($hasPhone)
        @if ($unlock)<p class="mt-1 text-xs font-semibold text-teal-700">{{ $unlock->expires_at ? __('Contact unlocked until :date', ['date' => $unlock->expires_at->format('d-m-Y')]) : __('Contact unlocked') }}</p>@endif
        <p class="mt-1 text-lg font-extrabold tracking-wide" data-number>{{ $revealed['display'] ?? \App\Models\ListingContact::mask($c['mobile']) }}</p>
        <div class="mt-3 grid grid-cols-2 gap-2">
            @if (in_array('call', $modes, true))
                <button type="button" class="btn-primary" data-contact-mode="call"><x-icon name="phone" class="h-4 w-4" /> {{ __('Call') }}</button>
            @endif
            @if (in_array('whatsapp', $modes, true))
                <button type="button" class="btn btn-pill bg-[#25D366] text-white hover:bg-[#1da851]" data-contact-mode="whatsapp"><x-icon name="chat" class="h-4 w-4" /> {{ __('WhatsApp') }}</button>
            @endif
            @if (in_array('call', $modes, true))
                <button type="button" class="btn-light col-span-2" data-contact-mode="show"><x-icon name="eye" class="h-4 w-4" /> {{ __('Show number') }}</button>
            @endif
        </div>
        {{-- Asked once per visit: name, mobile, consent. Works without JavaScript too (posts and comes back with the number). --}}
        <form method="POST" action="{{ route('market.contact.reveal', [$kind, $ownerId]) }}" class="mt-4 space-y-3 rounded-xl bg-page p-3 {{ $person ? 'hidden' : '' }}" data-contact-form id="{{ $formId }}">
            @csrf
            <input type="hidden" name="mode" value="show" data-contact-mode-input>
            @if ($src)<input type="hidden" name="src" value="{{ $src }}">@endif
            <div class="hidden" aria-hidden="true"><label>{{ __('Website') }} <input name="website" tabindex="-1" autocomplete="off"></label></div>
            <p class="text-sm font-semibold">{{ __('Share your details to see the number') }}</p>
            <x-field name="name" :label="__('Your name')" :value="$person['name'] ?? $customer?->name" required id="{{ $formId }}_name" />
            <x-field name="mobile" type="tel" :label="__('Mobile')" :value="$person['mobile'] ?? $customer?->mobile" required inputmode="numeric" maxlength="10" id="{{ $formId }}_mobile" />
            <label class="flex items-start gap-2 text-xs"><input type="checkbox" name="consent" value="1" class="checkbox mt-0.5" required> <span>{{ __('I agree to share my name and mobile with :name so they can contact me about this property.', ['name' => $c['name']]) }}</span></label>
            <p class="error hidden" data-contact-error role="alert"></p>
            <button class="btn-primary w-full">{{ __('Continue') }}</button>
        </form>
    @endif

    {{-- Free for every buyer: ask the owner to call back (becomes a lead in the owner's workspace) --}}
    <details class="mt-4 rounded-xl ring-1 ring-navy-50" @if ($cbOpen) open @endif>
        <summary class="cursor-pointer p-3 text-sm font-semibold"><x-icon name="phone" class="inline h-4 w-4" /> {{ __('Request a call back') }} <span class="badge-teal ml-1">{{ __('Free') }}</span></summary>
        <form method="POST" action="{{ route('market.contact.callback', [$kind, $ownerId]) }}" class="space-y-3 px-3 pb-3">
            @csrf
            @if ($src)<input type="hidden" name="src" value="{{ $src }}">@endif
            <div class="hidden" aria-hidden="true"><label>{{ __('Website') }} <input name="website" tabindex="-1" autocomplete="off"></label></div>
            <p class="text-xs text-muted">{{ __(':name will contact you the way you prefer.', ['name' => $c['name']]) }}</p>
            <x-field name="name" :label="__('Your name')" :value="$person['name'] ?? $customer?->name" required id="{{ $formId }}_cname" />
            <x-field name="mobile" type="tel" :label="__('Mobile')" :value="$person['mobile'] ?? $customer?->mobile" required inputmode="numeric" maxlength="10" id="{{ $formId }}_cmobile" />
            <x-field name="email" type="email" :label="__('Email (optional)')" :value="$customer?->email" id="{{ $formId }}_cemail" />
            <div class="grid grid-cols-2 gap-3">
                <x-select name="preferred_mode" :label="__('Contact me by')" :options="collect(\App\Http\Controllers\Market\ContactController::CALLBACK_MODES)->map(fn ($l) => __($l))->all()" value="call" id="{{ $formId }}_cmode" />
                <x-select name="preferred_slot" :label="__('Best time')" :options="collect(\App\Http\Controllers\Market\ContactController::CALLBACK_SLOTS)->map(fn ($l) => __($l))->all()" value="any" id="{{ $formId }}_cslot" />
            </div>
            <x-field name="preferred_date" type="date" :label="__('Preferred day (optional)')" :min="today()->toDateString()" id="{{ $formId }}_cdate" />
            <x-textarea name="message" :label="__('Message (optional)')" rows="2" id="{{ $formId }}_cmsg" />
            <label class="flex items-start gap-2 text-xs"><input type="checkbox" name="consent" value="1" class="checkbox mt-0.5" required> <span>{{ __('I agree to share these details with :name.', ['name' => $c['name']]) }}</span></label>
            <button class="btn-light w-full">{{ __('Request call back') }}</button>
        </form>
    </details>
    <p class="mt-3 text-xs text-muted">{{ __('Never pay a token amount before visiting the site and checking documents.') }}</p>
</section>
