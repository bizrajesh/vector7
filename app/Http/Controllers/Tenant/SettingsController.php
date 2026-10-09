<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\IdSequence;
use App\Models\InstalmentPlan;
use App\Models\RefundPenaltyRule;
use App\Services\FileStore;
use App\Services\IdGenerator;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Tenant Settings (spec 7.3): organisation, sales rules, refund penalties, disclaimers, ID seeds, calendar. */
class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $tenant = app(Tenancy::class)->get();

        return view('ws.settings.index', [
            'tenant' => $tenant,
            'settings' => $tenant->setting(),
            'instalments' => InstalmentPlan::orderBy('seq')->get(),
            'penalties' => RefundPenaltyRule::orderBy('from_days')->get(),
            'sequences' => IdSequence::pluck('prefix', 'type'),
            'holidays' => Holiday::orderBy('date')->get(),
            'tab' => $request->query('tab', 'organisation'),
        ]);
    }

    public function organisation(Request $request)
    {
        $tenant = app(Tenancy::class)->get();
        $url = ['nullable', 'url:https', 'max:255', 'starts_with:https://'];
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'address_line1' => 'required|string|max:150',
            'address_line2' => 'nullable|string|max:150',
            'village' => 'nullable|string|max:100',
            'city' => 'required|string|max:100',
            'district' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pin' => ['required', 'regex:/^\d{6}$/'],
            'contact' => ['required', 'regex:/^\d{10}$/'],
            'alt_contact' => ['nullable', 'regex:/^\d{10,12}$/'],
            'support_email' => 'nullable|email|max:255',
            'website' => $url, 'instagram' => $url, 'facebook' => $url, 'youtube' => $url, 'linkedin' => $url,
            'logo' => FileStore::rules('logo', 2048, false),
        ], ['pin.regex' => 'PIN must be 6 digits.', 'contact.regex' => 'Contact must be a 10-digit mobile number.', '*.starts_with' => 'Links must start with https://']);
        unset($data['logo']);
        if ($request->hasFile('logo')) {
            $img = @getimagesize($request->file('logo')->getRealPath());
            if (! $img) {
                return back()->withErrors(['logo' => 'Upload a PNG or JPG image.']);
            }
            $file = FileStore::store($request->file('logo'), 'logo', $tenant->id, $tenant, 'logo');
            $data['logo_path'] = $file->path;
        }
        $tenant->update($data);

        return back()->with('ok', 'Organisation details saved.');
    }

    public function sales(Request $request)
    {
        $data = $request->validate([
            'booking_validity_days' => 'required|integer|min:1|max:90',
            'sale_window_days' => 'required|integer|min:1|max:365',
            'currency' => 'required|in:INR',
            'sellable_pct' => 'required|numeric|min:1|max:100',
            'broker_commission_pct' => 'required|numeric|min:0|max:100',
            'budget_alert_pct' => 'required|numeric|min:1|max:100',
            'mrp_multiplier' => 'required|numeric|min:0.1|max:20',
            'inst' => 'required|array|min:1|max:12',
            'inst.*.name' => 'required|string|max:60',
            'inst.*.percent' => 'required|numeric|min:0.01|max:100',
            'inst.*.due_working_days' => 'required|integer|min:0|max:365',
            'pen' => 'array',
            'pen.*.from_days' => 'required|integer|min:0|max:3650',
            'pen.*.to_days' => 'nullable|integer|min:0|max:3650',
            'pen.*.penalty_type' => 'required|in:fixed,percent',
            'pen.*.value' => 'required|numeric|min:0',
        ]);
        $inst = array_values($data['inst']);
        $total = round(array_sum(array_column($inst, 'percent')), 2);
        if (abs($total - 100) > 0.001) {
            return back()->withInput()->with('error', "Instalment percentages must total 100% (now $total%).");
        }
        $lastDue = max(array_column($inst, 'due_working_days'));
        if ($lastDue > $data['sale_window_days']) {
            return back()->withInput()->with('error', "The last instalment is due on working day $lastDue, which is after the sale completion window ({$data['sale_window_days']} working days).");
        }
        if ((float) $inst[0]['percent'] < 1) {
            return back()->withInput()->with('error', 'The first instalment must be at least 1%.');
        }
        foreach (array_values($data['pen'] ?? []) as $p) {
            if ($p['to_days'] !== null && $p['to_days'] < $p['from_days']) {
                return back()->withInput()->with('error', 'In the refund penalty table, "to" days must be after "from" days.');
            }
            if ($p['penalty_type'] === 'percent' && $p['value'] > 100) {
                return back()->withInput()->with('error', 'A percentage penalty cannot be more than 100%.');
            }
        }
        $tenant = app(Tenancy::class)->get();
        DB::transaction(function () use ($tenant, $data, $inst) {
            $tenant->setting()->update(collect($data)->only(['booking_validity_days', 'sale_window_days', 'currency', 'sellable_pct', 'broker_commission_pct', 'budget_alert_pct', 'mrp_multiplier'])->all());
            InstalmentPlan::query()->delete();
            foreach ($inst as $i => $row) {
                InstalmentPlan::create(['tenant_id' => $tenant->id, 'seq' => $i + 1] + $row);
            }
            RefundPenaltyRule::query()->delete();
            foreach (array_values($data['pen'] ?? []) as $p) {
                RefundPenaltyRule::create(['tenant_id' => $tenant->id] + $p);
            }
        });

        return redirect()->route('ws.settings.index', ['tab' => 'sales'])->with('ok', 'Sales rules saved.');
    }

    public function disclaimers(Request $request)
    {
        $data = $request->validate([
            'disclaimer_booking' => 'required|string|max:20000',
            'disclaimer_sale' => 'required|string|max:20000',
            'disclaimer_registration' => 'required|string|max:20000',
            'disclaimer_refund' => 'required|string|max:20000',
        ]);
        app(Tenancy::class)->get()->setting()->update($data);

        return redirect()->route('ws.settings.index', ['tab' => 'disclaimers'])->with('ok', 'Disclaimers saved.');
    }

    public function ids(Request $request)
    {
        $rules = [];
        foreach (array_keys(IdGenerator::TYPES) as $t) {
            $rules["prefix.$t"] = ['required', 'regex:/^[A-Z0-9]{1,6}$/'];
        }
        $data = $request->validate($rules, ['prefix.*.regex' => 'Use 1–6 capital letters or digits.']);
        $tenantId = app(Tenancy::class)->id();
        foreach ($data['prefix'] as $type => $prefix) {
            IdSequence::updateOrCreate(['tenant_id' => $tenantId, 'type' => $type], ['prefix' => $prefix]);
        }

        return redirect()->route('ws.settings.index', ['tab' => 'ids'])->with('ok', 'ID prefixes saved. New records use them from now on.');
    }

    public function addHoliday(Request $request)
    {
        $tenantId = app(Tenancy::class)->id();
        $data = $request->validate(['date' => 'required|date', 'name' => 'required|string|max:100']);
        Holiday::updateOrCreate(['tenant_id' => $tenantId, 'date' => $data['date']], ['name' => $data['name']]);

        return redirect()->route('ws.settings.index', ['tab' => 'calendar'])->with('ok', 'Holiday added.');
    }

    public function deleteHoliday(Holiday $holiday)
    {
        $holiday->delete();

        return redirect()->route('ws.settings.index', ['tab' => 'calendar'])->with('ok', 'Holiday removed.');
    }
}
