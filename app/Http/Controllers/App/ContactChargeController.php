<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ContactUnlock;
use App\Services\AppSettings;
use App\Services\AuditLogger;
use App\Services\ContactCharges;
use App\Services\PaymentLinks;
use App\Support\Excel;
use Illuminate\Http\Request;

/**
 * Contact view charges (same for every tenant's listings): buyers pay to see an owner's number; the money goes to the
 * App owner. Also the free-listing allowance and the photo size limit. Shows contact-view payments and revenue.
 */
class ContactChargeController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = ($request->date('to') ?? now())->copy()->endOfDay();
        $q = ContactUnlock::with(['customer:id,name,mobile,email', 'tenantRel:id,name,code'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->whereBetween('created_at', [$from, $to])->latest('id');
        if ($request->query('export') === 'xlsx') {
            return Excel::download('contact-views-'.now()->format('Ymd').'.xlsx',
                ['Receipt', 'Date', 'Buyer', 'Mobile', 'Property', 'Owner (tenant)', 'Amount', 'GST', 'Total', 'Status', 'Method', 'Payment ID', 'Valid until'],
                $q->get()->map(fn ($u) => [$u->number, $u->created_at->format('d-m-Y H:i'), $u->customer?->name, $u->customer?->mobile, $u->title, $u->tenantRel?->name,
                    (float) $u->amount, (float) $u->tax, (float) $u->total, $u->status, $u->method, $u->gateway_payment_id, $u->expires_at?->format('d-m-Y')])->all(), 'Contact views');
        }
        $paid = ContactUnlock::where('status', 'paid')->whereBetween('paid_at', [$from, $to]);

        return view('app.plans.contact-charges', [
            'unlocks' => $q->paginate(30)->withQueryString(),
            'totals' => ['count' => (clone $paid)->count(), 'amount' => (float) (clone $paid)->sum('amount'), 'tax' => (float) (clone $paid)->sum('tax'), 'total' => (float) (clone $paid)->sum('total')],
            'from' => $from, 'to' => $to,
            'gatewayOn' => PaymentLinks::enabled(),
            'types' => ContactCharges::typeOptions(),
        ]);
    }

    public function update(Request $request)
    {
        $rules = [
            'contact_charge_enabled' => 'nullable|boolean',
            'contact_charge_listing' => 'required|numeric|min:0|max:10000',
            'contact_charge_layout' => 'required|numeric|min:0|max:10000',
            'contact_validity_days' => 'required|integer|min:0|max:3650',
            'listings_free' => 'required|integer|min:0|max:1000',
            'listings_image_max_mb' => 'required|integer|min:1|max:20',
        ];
        foreach (array_keys(ContactCharges::typeOptions()) as $type) {
            $rules['contact_charge_type_'.$type] = 'nullable|numeric|min:0|max:10000';
        }
        $v = $request->validate($rules);
        $values = [
            'contact.charge_enabled' => $request->boolean('contact_charge_enabled') ? '1' : '0',
            'contact.charge_listing' => (string) $v['contact_charge_listing'],
            'contact.charge_layout' => (string) $v['contact_charge_layout'],
            'contact.validity_days' => (string) $v['contact_validity_days'],
            'listings.free' => (string) $v['listings_free'],
            'listings.image_max_mb' => (string) $v['listings_image_max_mb'],
        ];
        foreach (array_keys(ContactCharges::typeOptions()) as $type) {
            $values['contact.charge_type_'.$type] = isset($v['contact_charge_type_'.$type]) && $v['contact_charge_type_'.$type] !== null ? (string) $v['contact_charge_type_'.$type] : '';
        }
        foreach ($values as $key => $value) {
            AppSettings::set($key, $value);
        }
        AuditLogger::log('contact_charges_updated', null, null, $values);

        return back()->with('ok', 'Charges saved.');
    }
}
