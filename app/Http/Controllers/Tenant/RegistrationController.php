<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\DocumentChecklist;
use App\Models\Plot;
use App\Models\Project;
use App\Models\Registration;
use App\Models\RegistrationParty;
use App\Models\RegistrationWitness;
use App\Models\Sale;
use App\Models\Sro;
use App\Services\FileStore;
use App\Services\Notify;
use App\Support\Pdf;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Registration (spec 7.6): ROR plots → init (checklist, date, SRO auto-picked by district, seller & buyer,
 * plot details, boundaries, 2 witnesses) → print pack → attach documents → submit to document writer (ROR-Init)
 * → registered document no. & date + acknowledgement (ROR-Completed) → physical file no. (Sold).
 */
class RegistrationController extends Controller
{
    public function index(Request $request)
    {
        $ror = Sale::where('status', 'ror')->whereDoesntHave('registration')->with(['plot', 'project', 'customer'])
            ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->query('location'), fn ($q, $l) => $q->whereHas('project', fn ($w) => $w->where('location', $l)))
            ->get();
        $regs = Registration::with(['plot', 'project', 'customer', 'sro'])->latest('id')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->query('location'), fn ($q, $l) => $q->whereHas('project', fn ($w) => $w->where('location', $l)))
            ->paginate(25)->withQueryString();

        return view('ws.registration.index', [
            'ror' => $ror, 'registrations' => $regs,
            'projects' => Project::launched()->orderBy('name')->pluck('name', 'id'),
            'locations' => Project::launched()->distinct()->pluck('location'),
        ]);
    }

    public function create(Sale $sale)
    {
        abort_unless($sale->status === 'ror', 404);
        if ($sale->registration) {
            return redirect()->route('ws.registrations.show', $sale->registration);
        }
        $sale->load(['plot', 'project', 'customer']);
        $tenant = app(Tenancy::class)->get();

        return view('ws.registration.form', [
            'sale' => $sale,
            'tenant' => $tenant,
            'sros' => Sro::orderBy('district')->orderBy('name')->get(),
            'sro' => self::pickSro($sale->project),
            'checklist' => self::checklistItems(),
            'disclaimer' => $tenant->setting()->disclaimer_registration,
        ]);
    }

    /** SRO auto-picked from the project's district (and taluk/location when it matches). */
    public static function pickSro(Project $project): ?Sro
    {
        $q = Sro::where('district', $project->district);

        return (clone $q)->where(fn ($w) => $w->where('taluk', $project->location)->orWhere('name', 'like', '%'.$project->location.'%'))->first() ?? $q->first();
    }

    public static function checklistItems(): array
    {
        $items = DocumentChecklist::where('checklist', 'Plot Sale')->where('is_active', true)->orderBy('sino')->get()
            ->map(fn ($d) => ['code' => $d->doc_code, 'name' => $d->name, 'mandatory' => $d->mandatory === 'Yes', 'done' => false])->values()->all();

        return $items ?: [
            ['code' => 'SAL-06', 'name' => 'Buyer KYC – PAN, Aadhaar, photographs', 'mandatory' => true, 'done' => false],
            ['code' => 'SAL-13', 'name' => 'Stamp duty and registration fee payment', 'mandatory' => true, 'done' => false],
            ['code' => 'SAL-14', 'name' => 'Draft sale deed', 'mandatory' => true, 'done' => false],
        ];
    }

    public function store(Request $request, Sale $sale)
    {
        abort_unless($sale->status === 'ror' && ! $sale->registration, 404);
        $data = $this->validated($request);
        $request->validate(['disclaimer' => 'accepted'], ['disclaimer.accepted' => 'The buyer must accept the registration disclaimer.']);
        $reg = DB::transaction(function () use ($sale, $data, $request) {
            $reg = Registration::create([
                'tenant_id' => $sale->tenant_id, 'project_id' => $sale->project_id, 'plot_id' => $sale->plot_id, 'sale_id' => $sale->id, 'customer_id' => $sale->customer_id,
                'status' => 'draft', 'registration_date' => $data['registration_date'], 'sro_id' => $data['sro_id'], 'pr_numbers' => $data['pr_numbers'] ?? null,
                'document_writer' => $data['document_writer'] ?? null, 'checklist' => self::checklistFromRequest($request), 'disclaimer_accepted_at' => now(), 'created_by' => $request->user()->id,
            ]);
            $this->saveParties($reg, $data);
            $this->savePlot($sale->plot, $data);

            return $reg;
        });

        return redirect()->route('ws.registrations.show', $reg)->with('ok', 'Registration prepared. Print the pack, attach the documents, then submit it to the document writer.');
    }

    public function show(Request $request, Registration $registration)
    {
        $registration->load(['plot', 'project', 'customer', 'sale', 'sro', 'parties', 'witnesses', 'files']);

        return view('ws.registration.show', ['r' => $registration, 'sros' => Sro::orderBy('district')->orderBy('name')->get(), 'viewer' => $request->user()]);
    }

    public function update(Request $request, Registration $registration)
    {
        if (in_array($registration->status, ['ror_completed', 'sold'], true)) {
            return back()->with('error', 'This registration is completed and can no longer be edited.');
        }
        if ($request->has('checklist_only')) {
            $registration->update(['checklist' => self::checklistFromRequest($request, $registration->checklist)]);

            return back()->with('ok', 'Checklist saved.');
        }
        // A masked PAN (ABCDE****F) left unchanged keeps the stored, encrypted value.
        $keep = [];
        foreach (['seller', 'buyer'] as $t) {
            if (str_contains((string) $request->input($t.'_pan'), '*')) {
                $keep[$t] = $registration->parties()->where('party_type', $t)->value('pan_encrypted');
                $request->merge([$t.'_pan' => null]);
            }
        }
        $data = $this->validated($request);
        DB::transaction(function () use ($registration, $data, $request, $keep) {
            $registration->update([
                'registration_date' => $data['registration_date'], 'sro_id' => $data['sro_id'], 'pr_numbers' => $data['pr_numbers'] ?? null,
                'document_writer' => $data['document_writer'] ?? null, 'checklist' => self::checklistFromRequest($request, $registration->checklist), 'updated_by' => $request->user()->id,
            ]);
            $registration->parties()->delete();
            $registration->witnesses()->delete();
            $this->saveParties($registration, $data);
            foreach ($keep as $t => $enc) {
                $registration->parties()->where('party_type', $t)->update(['pan_encrypted' => $enc]);
            }
            $this->savePlot($registration->plot, $data);
        });

        return back()->with('ok', 'Registration details saved.');
    }

    public function upload(Request $request, Registration $registration)
    {
        $request->validate(['file' => FileStore::rules('document', 20480), 'label' => 'nullable|string|max:100']);
        FileStore::store($request->file('file'), 'registration', $registration->tenant_id, $registration);

        return back()->with('ok', 'Document attached.');
    }

    /** Submit to the document writer → ROR-Init. All mandatory checklist items ticked and documents attached. */
    public function submit(Registration $registration)
    {
        if ($registration->status !== 'draft') {
            return back()->with('error', 'Already submitted.');
        }
        $missing = collect($registration->checklist ?? [])->filter(fn ($i) => ($i['mandatory'] ?? false) && empty($i['done']))->pluck('code');
        if ($missing->isNotEmpty()) {
            return back()->with('error', 'Tick every mandatory checklist item first: '.$missing->implode(', ').'.');
        }
        if (! $registration->files()->exists()) {
            return back()->with('error', 'Attach the required documents first.');
        }
        if ($registration->witnesses()->count() < 2) {
            return back()->with('error', 'Two witnesses are required.');
        }
        DB::transaction(function () use ($registration) {
            $registration->update(['status' => 'ror_init', 'submitted_at' => now()]);
            Plot::whereKey($registration->plot_id)->lockForUpdate()->first()->moveTo('ror_init', 'Submitted to document writer');
        });
        $this->notify($registration, 'ROR-Init', 'Your documents are with the document writer. Registration date: '.$registration->registration_date?->format('d-m-Y').' at '.$registration->sro?->name.'.');

        return back()->with('ok', 'Submitted to the document writer. Status: ROR-Init.');
    }

    /** Registered document number & date → ROR-Completed; acknowledgement can be printed. */
    public function complete(Request $request, Registration $registration)
    {
        if ($registration->status !== 'ror_init') {
            return back()->with('error', 'Submit the registration first.');
        }
        $data = $request->validate(['registered_doc_no' => 'required|string|max:60', 'registered_doc_date' => 'required|date|before_or_equal:today']);
        DB::transaction(function () use ($registration, $data) {
            $registration->update($data + ['status' => 'ror_completed', 'completed_at' => now()]);
            Plot::whereKey($registration->plot_id)->lockForUpdate()->first()->moveTo('ror_completed', 'Registered as '.$data['registered_doc_no']);
        });
        $this->notify($registration, 'ROR-Completed', 'Registered document no. '.$data['registered_doc_no'].' dated '.date('d-m-Y', strtotime($data['registered_doc_date'])).'.');

        return back()->with('ok', 'Registration completed. Print the customer acknowledgement.');
    }

    /** Physical file number → Sold; the plot leaves the marketplace showcase. */
    public function sold(Request $request, Registration $registration)
    {
        if ($registration->status !== 'ror_completed') {
            return back()->with('error', 'Complete the registration first.');
        }
        $data = $request->validate(['physical_file_no' => 'required|string|max:60']);
        DB::transaction(function () use ($registration, $data) {
            $registration->update($data + ['status' => 'sold', 'sold_at' => now()]);
            Plot::whereKey($registration->plot_id)->lockForUpdate()->first()->moveTo('sold', 'File '.$data['physical_file_no']);
            $registration->sale->update(['status' => 'completed', 'active_plot_lock' => null]);
        });
        $this->notify($registration, 'Sold', 'Thank you for buying with us.');

        return back()->with('ok', 'Plot marked Sold and removed from the marketplace.');
    }

    public function pack(Request $request, Registration $registration)
    {
        $registration->load(['plot', 'project', 'customer', 'sale.payments', 'sro', 'parties', 'witnesses']);
        $tenant = app(Tenancy::class)->get();

        return Pdf::inline('pdf.registration-pack', ['r' => $registration, 'tenant' => $tenant, 'viewer' => $request->user(), 'disclaimer' => $tenant->setting()->disclaimer_registration], 'Registration-pack-'.$registration->plot->plot_no.'.pdf');
    }

    public function acknowledgement(Registration $registration)
    {
        abort_unless(in_array($registration->status, ['ror_completed', 'sold'], true), 404);
        $registration->load(['plot', 'project', 'customer', 'sale', 'sro']);

        return Pdf::inline('pdf.acknowledgement', ['r' => $registration, 'tenant' => app(Tenancy::class)->get()], 'Acknowledgement-'.$registration->plot->plot_no.'.pdf');
    }

    private function validated(Request $request): array
    {
        $pan = ['nullable', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'];

        return $request->validate([
            'registration_date' => 'required|date',
            'sro_id' => 'required|integer|exists:sros,id',
            'pr_numbers' => 'nullable|string|max:255',
            'document_writer' => 'nullable|string|max:150',
            'seller_name' => 'required|string|max:150', 'seller_relation' => 'nullable|string|max:150', 'seller_age' => 'nullable|integer|min:18|max:120',
            'seller_address' => 'required|string|max:500', 'seller_mobile' => ['nullable', 'regex:/^\d{10}$/'], 'seller_pan' => $pan,
            'buyer_name' => 'required|string|max:150', 'buyer_relation' => 'nullable|string|max:150', 'buyer_age' => 'nullable|integer|min:18|max:120',
            'buyer_address' => 'required|string|max:500', 'buyer_mobile' => ['nullable', 'regex:/^\d{10}$/'], 'buyer_pan' => $pan,
            'w' => 'required|array|size:2',
            'w.*.name' => 'required|string|max:150', 'w.*.relation_name' => 'nullable|string|max:150', 'w.*.age' => 'nullable|integer|min:18|max:120', 'w.*.address' => 'required|string|max:500',
            'east_boundary' => 'nullable|string|max:100', 'west_boundary' => 'nullable|string|max:100', 'north_boundary' => 'nullable|string|max:100', 'south_boundary' => 'nullable|string|max:100',
            'length_ft' => 'nullable|numeric|min:0', 'width_ft' => 'nullable|numeric|min:0',
        ], ['*.regex' => 'PAN must look like ABCDE1234F.', 'w.size' => 'Enter two witnesses.']);
    }

    private function saveParties(Registration $reg, array $d): void
    {
        foreach (['seller', 'buyer'] as $t) {
            $party = new RegistrationParty([
                'tenant_id' => $reg->tenant_id, 'registration_id' => $reg->id, 'party_type' => $t,
                'name' => $d[$t.'_name'], 'relation_name' => $d[$t.'_relation'] ?? null, 'age' => $d[$t.'_age'] ?? null,
                'address' => $d[$t.'_address'], 'mobile' => $d[$t.'_mobile'] ?? null,
            ]);
            $party->setPan($d[$t.'_pan'] ?? null);
            $party->save();
        }
        foreach ($d['w'] as $w) {
            RegistrationWitness::create(['tenant_id' => $reg->tenant_id, 'registration_id' => $reg->id, 'name' => $w['name'], 'relation_name' => $w['relation_name'] ?? null, 'age' => $w['age'] ?? null, 'address' => $w['address']]);
        }
    }

    private function savePlot(Plot $plot, array $d): void
    {
        $plot->update(array_filter([
            'east_boundary' => $d['east_boundary'] ?? null, 'west_boundary' => $d['west_boundary'] ?? null,
            'north_boundary' => $d['north_boundary'] ?? null, 'south_boundary' => $d['south_boundary'] ?? null,
            'length_ft' => $d['length_ft'] ?? null, 'width_ft' => $d['width_ft'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    private static function checklistFromRequest(Request $request, ?array $existing = null): array
    {
        $items = $existing ?: self::checklistItems();
        $done = (array) $request->input('checklist', []);

        return array_map(fn ($i) => array_merge($i, ['done' => in_array($i['code'], $done, true)]), $items);
    }

    private function notify(Registration $r, string $status, string $details): void
    {
        $r->loadMissing(['customer', 'plot', 'project']);
        Notify::send('registration_status', [$r->customer->email], ['name' => $r->customer->name, 'plot_no' => $r->plot->plot_no, 'project' => $r->project->name, 'status' => $status, 'details' => $details], $r->tenant_id);
        Notify::groups($r->tenant_id, ['Sales Team'], 'registration_status', ['name' => 'team', 'plot_no' => $r->plot->plot_no, 'project' => $r->project->name, 'status' => $status, 'details' => $details]);
    }
}
