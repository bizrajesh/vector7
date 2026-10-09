<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Plan;
use App\Models\Service;
use App\Services\Notify;
use App\Services\SeoService;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function services()
    {
        return view('market.services', [
            'services' => Service::where('is_active', true)->orderBy('sort')->get(),
            'seo' => SeoService::meta('page', 'services', 'Property services – legal, survey, loans', 'Legal title opinion, land survey, registration assistance, home-loan help and marketing for promoters.') + ['canonical' => route('market.services')],
            'jsonld' => [SeoService::breadcrumbs([['Home', route('home')], ['Services', route('market.services')]])],
        ]);
    }

    public function service(string $slug)
    {
        $s = Service::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return view('market.service', [
            'service' => $s,
            'seo' => SeoService::meta('service', $s->slug, $s->name.' – vector7 services', (string) ($s->summary ?: $s->description)) + ['canonical' => route('market.service', $s->slug)],
            'jsonld' => [SeoService::breadcrumbs([['Home', route('home')], ['Services', route('market.services')], [$s->name, route('market.service', $s->slug)]]), [
                '@context' => 'https://schema.org', '@type' => 'Service', 'name' => $s->name, 'description' => $s->summary, 'provider' => ['@type' => 'Organization', 'name' => 'vector7'],
                'areaServed' => 'Tamil Nadu', 'offers' => $s->price ? ['@type' => 'Offer', 'price' => (float) $s->price, 'priceCurrency' => 'INR'] : null,
            ]],
        ]);
    }

    public function about()
    {
        return view('market.about', ['seo' => SeoService::meta('page', 'about', 'About vector7', 'vector7 is a realty manage portal connecting plot buyers with trusted layout promoters.') + ['canonical' => route('market.about')]]);
    }

    public function support()
    {
        return view('market.support', ['seo' => SeoService::meta('page', 'support', 'Support & contact', 'Get help with your plot booking, payments, documents or your promoter workspace.') + ['canonical' => route('market.support')]]);
    }

    public function contact(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'mobile' => ['nullable', 'regex:/^\d{10}$/'],
            'message' => 'required|string|max:3000',
            'website' => 'nullable|max:0', // honeypot
        ]);
        unset($data['website']);
        Enquiry::create($data + ['tenant_id' => null, 'source' => 'contact', 'assigned_team' => 'app', 'customer_id' => auth('customer')->id()]);
        Notify::send('enquiry_received', [(string) \App\Services\AppSettings::get('org.support_email')], $data + ['project' => 'vector7 support', 'link' => route('app.enquiries.index')]);

        return back()->with('ok', 'Thanks — your message reached the vector7 team. We reply within one working day.');
    }

    public function pricing()
    {
        return view('market.pricing', [
            'plans' => Plan::where('is_active', true)->with('limits')->orderBy('sort')->get(),
            'seo' => SeoService::meta('page', 'pricing', 'vector7 for layout promoters – Plans', 'Run approvals, estimates, plot launch, bookings, payments and registration in one portal. Start a free trial.') + ['canonical' => route('market.pricing')],
            'jsonld' => [SeoService::softwareApplication(), SeoService::breadcrumbs([['Home', route('home')], ['For promoters', route('market.pricing')]])],
        ]);
    }

    public function privacy()
    {
        return view('market.legal', ['page' => 'privacy', 'seo' => ['title' => 'Privacy policy', 'description' => 'How vector7 collects, uses and protects your personal information.', 'canonical' => route('market.privacy')]]);
    }

    public function terms()
    {
        return view('market.legal', ['page' => 'terms', 'seo' => ['title' => 'Terms of service', 'description' => 'The terms that apply when you use the vector7 marketplace and promoter workspace.', 'canonical' => route('market.terms')]]);
    }
}
