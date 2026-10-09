<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Models\Sale;
use App\Services\SalesService;
use App\Support\Pdf;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Refunds after a missed sale completion window: request (Sales) → Admin approval with password → expense + plot released. */
class RefundController extends Controller
{
    public function index()
    {
        return view('ws.sales.refunds', ['refunds' => Refund::with(['sale.plot', 'sale.project', 'customer'])->latest('id')->paginate(25)]);
    }

    public function store(Request $request, Sale $sale)
    {
        try {
            $r = SalesService::requestRefund($sale, $request->user()->id);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('ok', 'Refund request sent for approval: '.\App\Support\Format::inr($r->refund_amount).' (penalty '.\App\Support\Format::inr($r->penalty_amount).').');
    }

    public function decide(Request $request, Refund $refund)
    {
        // Sensitive action: confirm with the approver's own password (no OTP in this release).
        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
            'notes' => 'nullable|string|max:500',
            'current_password' => ['required', 'current_password:web'],
        ], ['current_password.current_password' => 'Your password is not correct.', 'current_password.required' => 'Confirm with your password.']);
        abort_unless($request->user()->isTenantAdmin(), 403, 'Only a Tenant Admin can approve refunds.');
        try {
            SalesService::decideRefund($refund, $data['decision'] === 'approve', $data['notes'] ?? null, $request->user()->id);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('ok', $data['decision'] === 'approve' ? 'Refund approved, recorded as an expense, and the plot is available again.' : 'Refund rejected; the sale continues.');
    }

    public function note(Refund $refund)
    {
        $tenant = app(Tenancy::class)->get();

        return Pdf::inline('pdf.refund', ['refund' => $refund->load(['sale.plot', 'sale.project', 'customer']), 'tenant' => $tenant, 'disclaimer' => $tenant->setting()->disclaimer_refund], 'Refund-note-'.$refund->id.'.pdf');
    }
}
