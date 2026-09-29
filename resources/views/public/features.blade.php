<x-layouts.public :seo="$seo">
    <div class="mx-auto max-w-4xl px-4 py-12">
        <nav aria-label="Breadcrumb" class="text-sm text-ink-muted"><a href="{{ route('home') }}">Home</a> / <span>Features</span></nav>
        <h1 class="mt-3 text-4xl font-extrabold">Features for layout, sales and shareholder management</h1>
        <p class="mt-4 text-lg text-ink-2">Vector7 follows a layout from the first site visit to the last registration. Here is what each part of the workflow does.</p>

        @foreach ([
            ['Operations and stage groups', 'Create reusable stage groups — for example Feasibility, Document Validation and Agri NOC — each with a sequence, cost, duration and whether it is mandatory. Link dependent stages so the system can compute planned dates and the critical path. Break stages into tasks with effort and weight; task effort can never exceed the stage duration. As work progresses, record expenses against each stage and receive email or WhatsApp alerts when spending crosses your budget threshold.'],
            ['Layout projects', 'A step-by-step wizard captures the layout, land owners, survey numbers and guideline values, documents such as Patta, Chitta and EC, and the facilities you will build: parks, roads, overhead tanks, drainage and more. Vector7 estimates total project cost, production value, sellable area and the number of plots. Submitting the project locks the estimate as a baseline.'],
            ['Launch and sales', 'When every mandatory stage is complete, import plots from a spreadsheet and launch. Sales staff see a colour-coded plot map on their phone, book a plot for 15 days, or sell it with a 30/60/10 instalment schedule inside 15 working days. Plots move automatically through Available, Booked, Ongoing-Sale, Ready for Registration, Ongoing-Registration and Sold.'],
            ['Registration', 'Generate a registration checklist for the document writer, track uploads of Patta, Chitta, EC and IDs, record the document number and Sub-Registrar office, and print a customer acknowledgement that confirms documents received and no dues.'],
            ['Manage shares', 'Allocate shares to partners by cash or land contribution. Shareholders cannot buy, sell or transfer shares; only plot sales change the share value. Every sale credits each shareholder with their portion of the realised profit, and payouts are tracked against those credits.'],
            ['Accounting and analytics', 'Every payment, expense, commission and payout lands in an append-only ledger. Dashboards show the sales funnel, collections ageing, monthly cash flow, project health and share growth.'],
        ] as [$title, $text])
            <section class="mt-10">
                <h2 class="text-2xl font-bold">{{ $title }}</h2>
                <p class="mt-2 text-ink-2">{{ $text }}</p>
            </section>
        @endforeach

        <div class="mt-12 flex flex-wrap gap-3">
            <a href="{{ route('register') }}" class="btn-primary">Start free trial</a>
            <a href="{{ route('pricing') }}" class="btn-outline">See pricing</a>
        </div>
    </div>
</x-layouts.public>
