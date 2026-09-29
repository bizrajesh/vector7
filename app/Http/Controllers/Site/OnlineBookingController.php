<?php

namespace App\Http\Controllers\Site;

use App\Enums\PlotStatus;
use App\Http\Controllers\Controller;
use App\Models\Plot;
use App\Models\Tenant;
use App\Services\OnlineBookingService;
use App\Services\OtpService;
use App\Services\PublicCatalog;
use App\Services\Settings;
use App\Support\SecurityLog;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Website booking: form → email one-time code → short hold → customer portal.
 * The pending details live server-side in the session; the browser never re-posts them.
 */
class OnlineBookingController extends Controller
{
    public function __construct(private readonly PublicCatalog $catalog, private readonly TenantContext $context) {}

    public function create(string $tenant, string $code, string $plotNo): View
    {
        [$tenantModel, $layout, $plot] = $this->resolve($tenant, $code, $plotNo);

        $hours = $this->context->run($tenantModel, fn () => (int) app(Settings::class)->get('online_hold_hours', 48));

        return view('public.booking.create', compact('tenantModel', 'layout', 'plot', 'hours'));
    }

    public function store(Request $request, string $tenant, string $code, string $plotNo, OtpService $otp): RedirectResponse
    {
        [$tenantModel, $layout, $plot] = $this->resolve($tenant, $code, $plotNo);

        // Honeypot: real users never fill the hidden "website" field.
        if (filled($request->input('website'))) {
            SecurityLog::warning('booking_honeypot', ['plot' => $plot->id]);

            return redirect()->route('projects.show', [$tenantModel->slug, Str::lower($layout->code)]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^[0-9+\-\s]{10,15}$/'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'consent' => ['accepted'],
        ]);

        abort_unless($plot->status === PlotStatus::Available, 422, 'Sorry, this plot is no longer available.');

        $request->session()->put('online_booking', [
            'tenant_id' => $tenantModel->id,
            'plot_id' => $plot->id,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => strtolower($data['email']),
            'back' => route('projects.show', [$tenantModel->slug, Str::lower($layout->code)]),
        ]);

        $otp->send($data['email'], 'booking');

        return redirect()->route('booking.verify');
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get('online_booking');
        if (! $pending) {
            return redirect()->route('projects.index');
        }

        return view('public.booking.verify', ['email' => $pending['email'], 'back' => $pending['back']]);
    }

    public function verify(Request $request, OtpService $otp, OnlineBookingService $bookings): RedirectResponse
    {
        $pending = $request->session()->get('online_booking');
        if (! $pending) {
            return redirect()->route('projects.index');
        }

        $request->validate(['code' => ['required', 'digits:6']]);
        $otp->verify($pending['email'], 'booking', $request->input('code'));

        $tenant = Tenant::query()->findOrFail($pending['tenant_id']);
        [$booking, $user] = $this->context->run($tenant, function () use ($pending, $bookings) {
            $plot = Plot::query()->findOrFail($pending['plot_id']);

            return $bookings->hold($plot, $pending);
        });

        $request->session()->forget('online_booking');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('portal.customer')->with('status', "Plot held until {$booking->expires_at->format('d M Y, h:i A')}. Our sales team will call you to collect the booking advance.");
    }

    public function resend(Request $request, OtpService $otp): RedirectResponse
    {
        $pending = $request->session()->get('online_booking');
        abort_unless($pending, 404);
        $otp->send($pending['email'], 'booking');

        return back()->with('status', 'A new code has been sent.');
    }

    /** @return array{0: Tenant, 1: \App\Models\Layout, 2: Plot} */
    private function resolve(string $tenant, string $code, string $plotNo): array
    {
        [$tenantModel, $layout] = $this->catalog->find($tenant, $code);
        $plot = $this->context->run($tenantModel, fn () => $layout->plots()->where('plot_no', $plotNo)->first());
        abort_unless($plot, 404);

        return [$tenantModel, $layout, $plot];
    }
}
