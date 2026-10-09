<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Enquiry;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Sale;
use App\Models\StorageFile;
use App\Services\SalesService;
use App\Support\Passwords;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** Customer account: bookings, purchases, payments, receipts, documents, enquiries, profile. Everything is filtered by customer_id. */
class AccountController extends Controller
{
    private function all(callable $fn)
    {
        return app(Tenancy::class)->withoutScope($fn);
    }

    public function dashboard(Request $request)
    {
        $c = $request->user('customer');

        return view('account.dashboard', $this->all(fn () => [
            'customer' => $c,
            'bookings' => Booking::where('customer_id', $c->id)->where('status', 'active')->with(['plot', 'project'])->get(),
            'sales' => Sale::where('customer_id', $c->id)->with(['plot', 'project', 'instalments'])->latest('id')->get(),
            'recentPayments' => Payment::where('customer_id', $c->id)->with(['plot', 'project'])->latest('paid_on')->take(5)->get(),
        ]));
    }

    public function bookings(Request $request)
    {
        $c = $request->user('customer');

        return view('account.bookings', $this->all(fn () => ['bookings' => Booking::where('customer_id', $c->id)->with(['plot', 'project.tenantRel'])->latest('id')->get()]));
    }

    public function purchases(Request $request)
    {
        $c = $request->user('customer');

        return view('account.purchases', $this->all(fn () => ['sales' => Sale::where('customer_id', $c->id)->with(['plot', 'project.tenantRel', 'instalments', 'registration'])->latest('id')->get()]));
    }

    public function payments(Request $request)
    {
        $c = $request->user('customer');

        return view('account.payments', $this->all(fn () => ['payments' => Payment::where('customer_id', $c->id)->with(['plot', 'project', 'receipt'])->latest('paid_on')->get()]));
    }

    public function receipt(Request $request, int $payment)
    {
        $p = $this->all(fn () => Payment::where('customer_id', $request->user('customer')->id)->findOrFail($payment));

        return response($this->all(fn () => SalesService::receiptPdf($p)), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Receipt-'.$p->transaction_no.'.pdf"']);
    }

    public function documents(Request $request)
    {
        $c = $request->user('customer');

        return view('account.documents', $this->all(function () use ($c) {
            $regs = Registration::where('customer_id', $c->id)->with(['plot', 'project'])->get();

            return [
                'registrations' => $regs,
                'files' => StorageFile::where('attachable_type', Registration::class)->whereIn('attachable_id', $regs->pluck('id'))->get()->groupBy('attachable_id'),
                'payments' => Payment::where('customer_id', $c->id)->with('receipt', 'plot', 'project')->latest('paid_on')->get(),
            ];
        }));
    }

    public function enquiries(Request $request)
    {
        $c = $request->user('customer');

        return view('account.enquiries', $this->all(fn () => ['enquiries' => Enquiry::where(fn ($q) => $q->where('customer_id', $c->id)->orWhere('email', $c->email))->with('project')->latest('id')->get()]));
    }

    public function requirements(Request $request)
    {
        return view('account.requirements', ['requirements' => $request->user('customer')->requirements()->latest('id')->get()]);
    }

    public function profile(Request $request)
    {
        return view('account.profile', ['c' => $request->user('customer')]);
    }

    public function updateProfile(Request $request)
    {
        $c = $request->user('customer');
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', Rule::unique('customers', 'email')->ignore($c->id)],
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pin' => ['nullable', 'regex:/^\d{6}$/'],
        ]);
        $c->update($data);

        return back()->with('ok', 'Profile saved.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate(['current_password' => ['required', 'current_password:customer'], 'password' => Passwords::rules()], Passwords::messages());
        $c = $request->user('customer');
        $c->forceFill(['password' => $request->password])->saveQuietly();
        Auth::guard('customer')->logoutOtherDevices($request->password);

        return back()->with('ok', 'Password changed.');
    }
}
