<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\PublicCatalog;
use App\Support\Seo;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(PublicCatalog $catalog): View
    {
        return view('public.home', [
            'seo' => Seo::make(
                'Plots for Sale – Live Availability, Online Booking',
                'Browse approved layout projects, see live plot availability and prices, and hold a plot online for 48 hours. The sales team helps you complete the purchase.',
                '/',
                schema: [Seo::organization(), Seo::website(), Seo::softwareApplication()],
            ),
            'plans' => Plan::query()->active()->get(),
            'featured' => $catalog->layouts(6),
        ]);
    }

    public function features(): View
    {
        return view('public.features', ['seo' => Seo::make(
            'Features – Layout, Sales & Shareholder Management',
            'Stage groups with dependencies, plot catalogue, bookings with auto-expiry, instalment tracking, registration checklist, share value per sale and DSS dashboards.',
            '/features',
            breadcrumbs: [['Features', '/features']],
        )]);
    }

    public function pricing(): View
    {
        $plans = Plan::query()->active()->get();

        return view('public.pricing', [
            'plans' => $plans,
            'seo' => Seo::make(
                'Pricing – Plans for Real Estate Layout Developers',
                'Choose Starter, Growth or Enterprise. Every plan includes the plot catalogue, bookings, payments and registration workflow. Free trial, GST extra.',
                '/pricing',
                breadcrumbs: [['Pricing', '/pricing']],
                schema: [Seo::offers($plans)],
            ),
        ]);
    }

    public function about(): View
    {
        return view('public.page', ['page' => 'about', 'seo' => Seo::make(
            'About Vector7 – Built for Layout Promoters, India',
            'Vector7 helps layout promoters and plot developers manage approvals, sales, shareholders and accounts in one secure, mobile-friendly workspace.',
            '/about', breadcrumbs: [['About', '/about']],
        )]);
    }

    public function contact(): View
    {
        return view('public.page', ['page' => 'contact', 'seo' => Seo::make(
            'Contact Vector7 – Demo, Support & Sales Enquiries',
            'Talk to the Vector7 team about a demo, onboarding your layouts or migrating plot and customer data. Email support and we reply within one business day.',
            '/contact', breadcrumbs: [['Contact', '/contact']],
        )]);
    }

    public function privacy(): View
    {
        return view('public.page', ['page' => 'privacy', 'seo' => Seo::make(
            'Privacy Policy – How Vector7 Protects Your Data',
            'How Vector7 collects, stores and protects business and personal data, including encryption of Aadhaar and PAN, tenant isolation and data retention.',
            '/privacy', breadcrumbs: [['Privacy', '/privacy']],
        )]);
    }

    public function terms(): View
    {
        return view('public.page', ['page' => 'terms', 'seo' => Seo::make(
            'Terms of Service – Vector7 Subscription Agreement',
            'The terms that govern use of the Vector7 platform: subscriptions, free trials, billing, acceptable use, data ownership and termination of service.',
            '/terms', breadcrumbs: [['Terms', '/terms']],
        )]);
    }
}
