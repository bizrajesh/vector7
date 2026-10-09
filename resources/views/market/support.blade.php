<x-layouts.public :seo="$seo">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-2">
        <div>
            <h1 class="text-4xl font-extrabold tracking-tight">Support</h1>
            <p class="mt-3 text-lg text-muted">Questions about a booking, a payment receipt or your workspace? Write to us — we reply within one working day.</p>
            <dl class="mt-8 space-y-4">
                <div><dt class="text-sm text-muted">Email</dt><dd class="text-lg font-bold"><a href="mailto:{{ \App\Services\AppSettings::get('org.support_email') }}">{{ \App\Services\AppSettings::get('org.support_email') }}</a></dd></div>
                @if (\App\Services\AppSettings::get('org.contact'))<div><dt class="text-sm text-muted">Phone</dt><dd class="text-lg font-bold">{{ \App\Services\AppSettings::get('org.contact') }}</dd></div>@endif
                <div><dt class="text-sm text-muted">Address</dt><dd>{{ \App\Services\AppSettings::get('org.address') }}</dd></div>
            </dl>
            <div class="mt-8 rounded-2xl bg-white p-5 shadow-card">
                <p class="font-bold">Already a customer?</p>
                <p class="mt-1 text-sm text-muted">Raise a ticket from your account to track replies in one place.</p>
                <a href="{{ route('account.tickets') }}" class="btn-light btn-sm mt-3">My tickets</a>
            </div>
        </div>
        <form method="POST" action="{{ route('market.contact') }}" class="card card-pad space-y-4">
            @csrf
            <h2 class="text-xl font-extrabold">Send us a message</h2>
            <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
            <x-field name="name" label="Name" required :value="auth('customer')->user()?->name" />
            <x-field name="email" type="email" label="Email" required :value="auth('customer')->user()?->email" />
            <x-field name="mobile" type="tel" label="Mobile" inputmode="numeric" maxlength="10" />
            <x-textarea name="message" label="How can we help?" rows="5" required />
            <button class="btn-primary btn-pill px-8">Send message</button>
        </form>
    </div>
</x-layouts.public>
