<x-layouts.public :seo="$seo">
    <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        @if ($page === 'privacy')
            <h1 class="text-4xl font-extrabold tracking-tight">Privacy policy</h1>
            <div class="mt-8 space-y-5 leading-relaxed">
                <p>This policy explains what personal information vector7 collects and how it is used.</p>
                <h2 class="text-xl font-bold">What we collect</h2>
                <p>Your name, email, mobile number and address when you create an account, book a plot or send an enquiry; details of bookings, payments and documents recorded by the promoter; and basic technical data (IP address, browser) for security.</p>
                <h2 class="text-xl font-bold">How we use it</h2>
                <p>To run your account, to share your enquiry or booking with the promoter of the project you chose, to send receipts and status emails, and to keep the service secure. PAN numbers are stored encrypted and shown masked.</p>
                <h2 class="text-xl font-bold">Who can see it</h2>
                <p>A promoter sees your details only if you enquire about, book or buy a plot in their project. We do not sell your data.</p>
                <h2 class="text-xl font-bold">Your choices</h2>
                <p>You can update your profile at any time and ask us to correct your details by writing to {{ \App\Services\AppSettings::get('org.support_email') }}.</p>
            </div>
        @else
            <h1 class="text-4xl font-extrabold tracking-tight">Terms of service</h1>
            <div class="mt-8 space-y-5 leading-relaxed">
                <p>These terms apply when you use the vector7 marketplace or a promoter workspace.</p>
                <h2 class="text-xl font-bold">Listings</h2>
                <p>Projects, plots, prices, offers and approval details are published by each promoter, who is responsible for their accuracy. Verify documents before you buy.</p>
                <h2 class="text-xl font-bold">Bookings and payments</h2>
                <p>Booking on vector7 is free. Payments are made to the promoter and recorded by the promoter in vector7. Booking validity, instalments, the sale completion window and refunds follow the disclaimers shown and accepted at each step.</p>
                <h2 class="text-xl font-bold">Promoter workspaces</h2>
                <p>Subscriptions are billed per plan. Usage limits apply as shown in each plan. Workspaces may be suspended for non-payment or misuse.</p>
                <h2 class="text-xl font-bold">Contact</h2>
                <p>{{ \App\Services\AppSettings::get('org.name') }}, {{ \App\Services\AppSettings::get('org.address') }} — {{ \App\Services\AppSettings::get('org.support_email') }}</p>
            </div>
        @endif
    </article>
</x-layouts.public>
