<x-layouts.workspace title="Start registration">
    <x-page-header title="Start registration" :subtitle="'Plot '.$sale->plot->plot_no.' · '.$sale->project->name.' · buyer '.$sale->customer->name" :back="route('ws.registrations.index')" />
    <form method="POST" action="{{ route('ws.registrations.store', $sale) }}" class="space-y-6">
        @csrf
        @include('ws.registration.fields', ['r' => null])
        <div class="card card-pad">
            <h2 class="section-title">Checklist (Plot Sale)</h2>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach ($checklist as $c)<x-checkbox name="checklist[]" :value="$c['code']" :label="$c['code'].' · '.$c['name'].($c['mandatory'] ? ' *' : '')" />@endforeach
            </div>
        </div>
        <div class="card card-pad space-y-3">
            <h2 class="section-title">Registration disclaimer</h2>
            <p class="whitespace-pre-line rounded-xl bg-page p-4 text-sm">{{ $disclaimer }}</p>
            <x-checkbox name="disclaimer" label="The buyer has read and accepted the registration disclaimer" />
            <button class="btn-primary">Save & prepare pack</button>
        </div>
    </form>
</x-layouts.workspace>
