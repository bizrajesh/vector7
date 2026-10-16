<x-layouts.public :seo="$seo">
    @php
        $answers = [
            'buying' => [[__('Is vector7 free for buyers?'), __('Yes. Searching, contacting owners and booking on vector7 is free for buyers.')], [__('How do I contact an owner?'), __('Open the property and tap Call, WhatsApp or Send an email enquiry. The owner gets your name and mobile only after you agree.')], [__('Can I get alerts for new properties?'), __('Yes. Sign in, search, and tap “Save search & alert me”. We email you when a matching property is listed.')]],
            'booking' => [[__('How do I book a plot?'), __('Open a layout, choose an available plot, tap Book now, sign in and accept the booking terms. Pay the first instalment to the promoter within the booking validity period.')], [__('Who do I pay?'), __('You pay the promoter directly (bank transfer, UPI, cheque or cash). vector7 never collects money from buyers.')], [__('Where are my receipts?'), __('Every payment the promoter records gets a numbered receipt in My account → Payments & receipts.')]],
            'documents' => [[__('What should I check before buying?'), __('Patta, chitta, the encumbrance certificate (EC), the layout approval (DTCP / CMDA / panchayat) and the parent documents. Our legal title opinion service can check them for you.')], [__('How does registration work?'), __('The promoter prepares the registration pack, books a slot at your Sub-Registrar Office and the sale deed is registered there.')]],
            'listing' => [[__('A listing looks wrong or fake'), __('Use “Report this listing” on the property page. Our team reviews every report and hides listings that break the rules.')]],
            'promoter' => [[__('How do I list my layout or property?'), __('Create a free workspace from “For promoters”, then use Quick launch for an approved layout or Property listings for a single property.')], [__('I cannot sign in to my workspace'), __('Use “Forgot password?” on the workspace sign-in page, or ask your Tenant Admin to reset your password.')]],
        ];
        $shown = $topic ? [$topic => $answers[$topic] ?? []] : $answers;
    @endphp
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <h1 class="text-4xl font-extrabold tracking-tight">{{ __('Support') }}</h1>
        <p class="mt-3 max-w-2xl text-lg text-muted">{{ __('Choose a topic to see quick answers, or write to us — we reply within one working day.') }}</p>
        <nav class="mt-6 flex flex-wrap gap-2" aria-label="{{ __('Support topics') }}">
            <a href="{{ route('market.support') }}" class="chip no-underline {{ ! $topic ? 'bg-navy text-white' : '' }}" @if (! $topic) aria-current="page" @endif>{{ __('All topics') }}</a>
            @foreach ($topics as $k => $label)
                <a href="{{ route('market.support', ['topic' => $k]) }}" class="chip no-underline {{ $topic === $k ? 'bg-navy text-white' : '' }}" @if ($topic === $k) aria-current="page" @endif>{{ __($label) }}</a>
            @endforeach
        </nav>
        <div class="mt-8 grid gap-10 lg:grid-cols-2">
            <div>
                @forelse ($shown as $k => $qa)
                    @if ($qa)
                        <section class="mb-6">
                            <h2 class="text-lg font-extrabold">{{ __($topics[$k]) }}</h2>
                            <div class="mt-3 space-y-2">
                                @foreach ($qa as [$qq, $aa])
                                    <details class="card card-pad"><summary class="cursor-pointer font-semibold">{{ $qq }}</summary><p class="mt-2 text-sm text-muted">{{ $aa }}</p></details>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @empty
                @endforelse
                <dl class="mt-8 space-y-4">
                    <div><dt class="text-sm text-muted">{{ __('Email') }}</dt><dd class="text-lg font-bold"><a href="mailto:{{ \App\Services\AppSettings::get('org.support_email') }}">{{ \App\Services\AppSettings::get('org.support_email') }}</a></dd></div>
                    @if (\App\Services\AppSettings::get('org.contact'))<div><dt class="text-sm text-muted">{{ __('Phone') }}</dt><dd class="text-lg font-bold">{{ \App\Services\AppSettings::get('org.contact') }}</dd></div>@endif
                    <div><dt class="text-sm text-muted">{{ __('Address') }}</dt><dd>{{ \App\Services\AppSettings::get('org.address') }}</dd></div>
                </dl>
                <div class="mt-8 rounded-2xl bg-white p-5 shadow-card">
                    <p class="font-bold">{{ __('Already a customer?') }}</p>
                    <p class="mt-1 text-sm text-muted">{{ __('Raise a ticket from your account to track replies in one place.') }}</p>
                    <a href="{{ route('account.tickets') }}" class="btn-light btn-sm mt-3">{{ __('My tickets') }}</a>
                </div>
            </div>
            <form method="POST" action="{{ route('market.contact') }}" class="card card-pad h-fit space-y-4">
                @csrf
                <h2 class="text-xl font-extrabold">{{ __('Send us a message') }}</h2>
                <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
                <x-select name="topic" :label="__('Topic')" :options="collect($topics)->map(fn ($l) => __($l))->all()" :value="old('topic', $topic)" :placeholder="__('Choose a topic')" />
                <x-field name="name" :label="__('Name')" required :value="auth('customer')->user()?->name" />
                <x-field name="email" type="email" :label="__('Email')" required :value="auth('customer')->user()?->email" />
                <x-field name="mobile" type="tel" :label="__('Mobile')" inputmode="numeric" maxlength="10" />
                <x-textarea name="message" :label="__('How can we help?')" rows="5" required />
                <button class="btn-primary btn-pill px-8">{{ __('Send message') }}</button>
            </form>
        </div>
    </div>
</x-layouts.public>
