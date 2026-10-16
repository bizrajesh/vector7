{{-- Public contact block (spec 17.5). Vars: $kind ('listing'|'project'), $ownerId, $c (['name','mobile','whatsapp','email','modes']), $title, $src --}}
@php
    $revealed = session('revealed')[$kind.':'.$ownerId] ?? null;
    $customer = auth('customer')->user();
    $person = session('contact_person') ?? ($customer?->mobile ? ['name' => $customer->name, 'mobile' => $customer->mobile] : null);
    $modes = $c['modes'];
    $formId = 'cf_'.$kind.$ownerId;
@endphp
<section class="card card-pad scroll-mt-24" id="contact" aria-labelledby="contact-h" data-contact data-url="{{ route('market.contact.reveal', [$kind, $ownerId]) }}" data-known="{{ $person ? '1' : '0' }}">
    <h2 id="contact-h" class="font-extrabold">{{ $kind === 'project' ? __('Contact the promoter') : __('Contact the owner') }}</h2>
    <p class="mt-1 text-sm"><strong>{{ $c['name'] }}</strong></p>
    @if (in_array('call', $modes, true) || in_array('whatsapp', $modes, true))
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

    @if (in_array('email', $modes, true))
        <details class="mt-4" @if ($errors->has('message') || (! in_array('call', $modes, true) && ! in_array('whatsapp', $modes, true))) open @endif>
            <summary class="cursor-pointer text-sm font-semibold"><x-icon name="mail" class="inline h-4 w-4" /> {{ __('Send an email enquiry') }}</summary>
            <form method="POST" action="{{ route('market.contact.email', [$kind, $ownerId]) }}" class="mt-3 space-y-3">
                @csrf
                @if ($src)<input type="hidden" name="src" value="{{ $src }}">@endif
                <div class="hidden" aria-hidden="true"><label>{{ __('Website') }} <input name="website" tabindex="-1" autocomplete="off"></label></div>
                <x-field name="name" :label="__('Your name')" :value="$person['name'] ?? $customer?->name" required id="{{ $formId }}_ename" />
                <x-field name="email" type="email" :label="__('Email')" :value="$customer?->email" required id="{{ $formId }}_email" />
                <x-field name="mobile" type="tel" :label="__('Mobile')" :value="$person['mobile'] ?? $customer?->mobile" required inputmode="numeric" maxlength="10" id="{{ $formId }}_emobile" />
                <x-textarea name="message" :label="__('Message')" rows="3" required :value="__('I am interested in :title. Please share more details.', ['title' => $title])" id="{{ $formId }}_msg" />
                <label class="flex items-start gap-2 text-xs"><input type="checkbox" name="consent" value="1" class="checkbox mt-0.5" required> <span>{{ __('I agree to share these details with :name.', ['name' => $c['name']]) }}</span></label>
                <button class="btn-light w-full">{{ __('Send enquiry') }}</button>
            </form>
        </details>
    @endif
    @if (! $modes)<p class="mt-2 text-sm text-muted">{{ __('The owner has not enabled contact options for this property.') }}</p>@endif
    <p class="mt-3 text-xs text-muted">{{ __('vector7 never charges buyers. Never pay a token amount before visiting the site and checking documents.') }}</p>
</section>
