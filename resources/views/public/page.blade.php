@php
    $content = [
        'about' => ['About Vector7', [
            'Vector7 was built for layout promoters and plot developers who run approvals, sales and partner accounts on paper, WhatsApp and spreadsheets. It brings every step — from feasibility to the final registration — into one workspace that works on a phone at the site and on a laptop in the office.',
            'Each business gets its own isolated workspace. Owners decide who sees what: the sales team works with plots and payments, shareholders see their share value and earnings, and buyers see their own plot, dues and receipts.',
        ]],
        'contact' => ['Contact us', [
            'For a demo, onboarding help or support, email '.config('vector7.support_email').'. We reply within one business day.',
            'Existing customers can also reach support from inside the app. Please never send Aadhaar, PAN or bank details by email.',
        ]],
        'privacy' => ['Privacy policy', [
            'We collect the business and personal information you enter to provide the service: account details, layout and plot records, customer and shareholder contact details, and payment records.',
            'Aadhaar, PAN and bank details are encrypted at rest and masked on screen. Documents are stored privately and are only available to authorised users of your workspace. Every workspace is isolated from every other.',
            'We keep data for as long as your subscription is active and for 90 days after cancellation so you can export it, then delete it. We do not sell personal data.',
        ]],
        'terms' => ['Terms of service', [
            'By creating a workspace you agree to use Vector7 lawfully and to keep your login credentials confidential. The person who registers the workspace is its administrator and is responsible for the users they invite.',
            'Subscriptions renew each billing period. If a payment is missed, the workspace enters a 7-day grace period and then becomes read-only until paid. You may cancel at any time; your data remains exportable for 90 days.',
            'Vector7 is provided on a commercially reasonable basis. It records business transactions but does not give legal or tax advice.',
        ]],
    ][$page];
@endphp
<x-layouts.public :seo="$seo">
    <article class="mx-auto max-w-3xl px-4 py-12">
        <nav aria-label="Breadcrumb" class="text-sm text-ink-muted"><a href="{{ route('home') }}">Home</a> / <span>{{ $content[0] }}</span></nav>
        <h1 class="mt-3 text-4xl font-extrabold">{{ $content[0] }}</h1>
        @foreach ($content[1] as $paragraph)
            <p class="mt-5 text-ink-2">{{ $paragraph }}</p>
        @endforeach
    </article>
</x-layouts.public>
