@php $plot = $registration->plot; $customer = $registration->sale->customer; @endphp
<x-layouts.print :title="'Acknowledgement · '.$plot->plot_no">
    <h1 class="text-center text-2xl font-extrabold text-navy">Acknowledgement & No-Dues Declaration</h1>
    <p class="mt-8">I, <strong>{{ $customer->name }}</strong>, hereby declare that I have verified and received the registered sale deed and all related original documents for the property described below, and that there are <strong>no dues</strong> payable to or by the seller, <strong>{{ $currentTenant->name ?? '' }}</strong>, in respect of this purchase.</p>
    <table class="table mt-6">
        <tbody>
            <tr><td class="w-1/3 font-semibold">Registration date</td><td>{{ $registration->registration_date->format('j M Y') }}</td></tr>
            <tr><td class="font-semibold">Document number</td><td>{{ $registration->document_no }}</td></tr>
            <tr><td class="font-semibold">Plot number</td><td>{{ $plot->plot_no }}</td></tr>
            <tr><td class="font-semibold">Survey number</td><td>{{ $plot->survey_no }}</td></tr>
            <tr><td class="font-semibold">Project</td><td>{{ $plot->layout->name }}</td></tr>
            <tr><td class="font-semibold">Customer name</td><td>{{ $customer->name }}</td></tr>
            <tr><td class="font-semibold">Sub-Registrar office</td><td>{{ $registration->office?->name }}</td></tr>
        </tbody>
    </table>
    <div class="mt-20 grid grid-cols-2 gap-10 text-sm">
        <div><p>______________________________</p><p class="mt-1 font-semibold">{{ $customer->name }} (Buyer)</p><p>Date:</p></div>
        <div><p>______________________________</p><p class="mt-1 font-semibold">For {{ $currentTenant->name ?? '' }} (Seller)</p><p>Date:</p></div>
    </div>
</x-layouts.print>
