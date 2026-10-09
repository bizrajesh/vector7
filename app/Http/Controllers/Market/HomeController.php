<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Models\Plot;
use App\Models\Project;
use App\Models\Service;
use App\Services\SeoService;

class HomeController extends Controller
{
    public function home()
    {
        $featured = Project::launched()->with(['plots', 'layoutFile', 'tenantRel'])
            ->orderByDesc('is_featured')->orderByDesc('launched_at')->take(6)->get()
            ->filter(fn ($p) => $p->plots->where('status', 'available')->isNotEmpty())->values();
        $stats = [
            'projects' => Project::launched()->count(),
            'available' => Plot::where('status', 'available')->whereHas('project', fn ($q) => $q->launched())->count(),
            'locations' => Project::launched()->distinct()->count('location'),
        ];
        $seo = SeoService::meta('page', 'home', 'Approved residential plots – live availability', (string) \App\Services\AppSettings::get('seo.default_description'));

        return view('market.home', [
            'featured' => $featured,
            'stats' => $stats,
            'locations' => Project::launched()->distinct()->orderBy('location')->pluck('location'),
            'services' => Service::where('is_active', true)->whereIn('available_to', ['customer', 'both'])->orderBy('sort')->take(4)->get(),
            'seo' => $seo + ['canonical' => route('home')],
            'jsonld' => [SeoService::organization(), SeoService::website()],
        ]);
    }
}
