<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\DocumentWriter;
use App\Models\Plot;
use App\Models\Registration;
use App\Models\RegistrationChecklistItem;
use App\Models\SubRegistrarOffice;
use App\Services\FileVault;
use App\Services\RegistrationService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationController extends Controller
{
    public function __construct(private readonly RegistrationService $service, private readonly FileVault $vault) {}

    public function store(Request $request, Plot $plot): RedirectResponse
    {
        $tenantId = app(TenantContext::class)->id();
        $data = $request->validate([
            'document_writer_id' => ['nullable', Rule::exists('document_writers', 'id')->where('tenant_id', $tenantId)],
            'document_writer_name' => ['nullable', 'required_without:document_writer_id', 'string', 'max:120'],
            'sub_registrar_office_id' => ['nullable', Rule::exists('sub_registrar_offices', 'id')->where('tenant_id', $tenantId)],
            'planned_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $registration = $this->service->initiate($plot, $data);

        return $this->done('Registration initiated. Complete the checklist.', 'app.registrations.show', $registration);
    }

    public function show(Registration $registration): View
    {
        return view('app.registrations.show', [
            'registration' => $registration->load(['plot.layout', 'sale.customer', 'items', 'writer', 'office']),
            'offices' => SubRegistrarOffice::query()->orderBy('name')->get(),
            'writers' => DocumentWriter::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function updateItem(Request $request, Registration $registration, RegistrationChecklistItem $item): RedirectResponse
    {
        abort_unless($registration->status === 'ongoing', 422, 'Registration is completed.');
        $data = $request->validate([
            'value' => ['nullable', 'string', 'max:255'],
            'date_value' => ['nullable', 'date'],
            'is_done' => ['nullable', 'boolean'],
            'file' => ['nullable', 'file', 'max:'.config('vector7.uploads.max_kb'), 'mimes:'.implode(',', config('vector7.uploads.mimes'))],
        ]);

        $path = $request->hasFile('file') ? $this->vault->store($request->file('file'), 'registrations') : null;
        $this->service->updateItem($item, $data, $path);

        return $this->done('Checklist updated.');
    }

    public function itemFile(Registration $registration, RegistrationChecklistItem $item): StreamedResponse
    {
        return $this->vault->download($item->file_path, $item->label);
    }

    public function complete(Request $request, Registration $registration): RedirectResponse
    {
        $data = $request->validate([
            'registration_date' => ['required', 'date', 'before_or_equal:today'],
            'document_no' => ['required', 'string', 'max:60'],
            'sub_registrar_office_id' => ['required', Rule::exists('sub_registrar_offices', 'id')->where('tenant_id', app(TenantContext::class)->id())],
            'deed' => ['nullable', 'file', 'max:'.config('vector7.uploads.max_kb'), 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $deed = $request->hasFile('deed') ? $this->vault->store($request->file('deed'), 'deeds') : null;
        $this->service->complete($registration, $data, $deed);

        return $this->done('Registration completed. The plot is now Sold.');
    }

    public function details(Registration $registration): View
    {
        return view('print.registration-details', ['registration' => $registration->load(['plot.layout.owners', 'sale.customer', 'items', 'writer', 'office'])]);
    }

    public function ack(Registration $registration): View
    {
        abort_unless($registration->status === 'completed', 422, 'Complete the registration first.');

        return view('print.ack', ['registration' => $registration->load(['plot.layout', 'sale.customer', 'office'])]);
    }

    public function deed(Registration $registration): StreamedResponse
    {
        return $this->vault->download($registration->deed_path, 'deed-'.$registration->document_no);
    }
}
