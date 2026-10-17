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
    /** Services menu: filter by who it is for (buyers / promoters) and search. */
    public function services(Request $request)
    {
        $for = in_array($request->query('for'), ['buyers', 'promoters'], true) ? $request->query('for') : null;
        $q = trim((string) $request->query('q', ''));

        return view('market.services', [
            'for' => $for, 'q' => $q,
            'services' => Service::where('is_active', true)
                ->when($for === 'buyers', fn ($w) => $w->whereIn('available_to', ['customer', 'both']))
                ->when($for === 'promoters', fn ($w) => $w->whereIn('available_to', ['tenant', 'both']))
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%$q%")->orWhere('summary', 'like', "%$q%")))
                ->orderBy('sort')->get(),
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

    public const SUPPORT_TOPICS = ['buying' => 'Buying or renting', 'booking' => 'Booking & payments', 'documents' => 'Documents & registration',
        'listing' => 'Report a listing', 'promoter' => 'Promoter workspace', 'other' => 'Something else'];

    /** Support menu: pick a topic → matching answers + the contact form pre-set to that topic. */
    public function support(Request $request)
    {
        $topic = array_key_exists((string) $request->query('topic'), self::SUPPORT_TOPICS) ? $request->query('topic') : null;

        return view('market.support', ['topic' => $topic, 'topics' => self::SUPPORT_TOPICS,'seo' => SeoService::meta('page', 'support', 'Support & contact', 'Get help with your plot booking, payments, documents or your promoter workspace.') + ['canonical' => route('market.support')]]);
    }

    public function contact(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'mobile' => ['nullable', 'regex:/^\d{10}$/'],
            'message' => 'required|string|max:3000',
            'topic' => 'nullable|in:'.implode(',', array_keys(self::SUPPORT_TOPICS)),
            'website' => 'nullable|max:0', // honeypot
        ], ['mobile.regex' => __('Enter a 10-digit mobile number.')]);
        if (! empty($data['topic'])) {
            $data['message'] = '['.self::SUPPORT_TOPICS[$data['topic']].'] '.$data['message']; // stored in English for the App team
        }
        unset($data['website'], $data['topic']);
        Enquiry::create($data + ['tenant_id' => null, 'source' => 'contact', 'assigned_team' => 'app', 'customer_id' => auth('customer')->id()]);
        Notify::send('enquiry_received', [(string) \App\Services\AppSettings::get('org.support_email')], $data + ['project' => 'vector7 support', 'link' => route('app.enquiries.index')]);

        return back()->with('ok', __('Thanks — your message reached the vector7 team. We reply within one working day.'));
    }

    /** For promoters menu: "I want to" — launch layouts, or post individual properties. */
    public function pricing(Request $request)
    {
        $for = $request->query('for') === 'listings' ? 'listings' : 'layouts';

        return view('market.pricing', [
            'for' => $for,
            // "Post individual properties" shows the Property lister plans; "Sell plots in layouts" the promoter plans.
            'plans' => Plan::where('is_active', true)->where('audience', $for === 'listings' ? 'lister' : 'promoter')->with('limits')->orderBy('sort')->get(),
            'seo' => SeoService::meta('page', 'pricing', 'vector7 for layout promoters – Plans', 'Run approvals, estimates, plot launch, bookings, payments and registration in one portal. Start a free trial.') + ['canonical' => route('market.pricing')],
            'jsonld' => [SeoService::softwareApplication(), SeoService::breadcrumbs([['Home', route('home')], ['For promoters', route('market.pricing')]])],
        ]);
    }

    public function privacy()
    {
        return view('market.legal', ['page' => 'privacy', 'seo' => ['title' => __('Privacy policy'), 'description' => __('How vector7 collects, uses and protects your personal information.'), 'canonical' => route('market.privacy')]]);
    }

    public function terms()
    {
        return view('market.legal', ['page' => 'terms', 'seo' => ['title' => __('Terms of service'), 'description' => __('The terms that apply when you use the vector7 marketplace and promoter workspace.'), 'canonical' => route('market.terms')]]);
    }
}
