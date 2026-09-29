<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\PublicCatalog;
use App\Support\Seo;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Public project list and plot map (indexable, no personal data). */
class ProjectController extends Controller
{
    public function index(PublicCatalog $catalog): View
    {
        return view('public.projects.index', [
            'layouts' => $catalog->layouts(),
            'seo' => Seo::make(
                'Plots for Sale – Browse Approved Layout Projects',
                'Browse launched layout projects, see live plot availability, size, facing and price, and hold a plot online. Purchases are completed with our sales team.',
                '/projects',
                breadcrumbs: [['Projects', '/projects']],
            ),
        ]);
    }

    public function show(string $tenant, string $code, PublicCatalog $catalog, TenantContext $context): View
    {
        [$tenantModel, $layout] = $catalog->find($tenant, $code);

        return $context->run($tenantModel, function () use ($tenantModel, $layout) {
            $plots = $layout->plots()->orderByRaw('LENGTH(plot_no), plot_no')
                ->get(['id', 'plot_no', 'size_sqft', 'rate_sqft', 'cost', 'facing', 'dimensions', 'boundary_north', 'boundary_south', 'boundary_east', 'boundary_west', 'status']);

            $path = '/projects/'.$tenantModel->slug.'/'.Str::lower($layout->code);
            $place = trim(collect([$layout->location, $layout->village, $layout->district])->filter()->implode(', '));

            $schema = [[
                '@context' => 'https://schema.org', '@type' => 'Place', 'name' => $layout->name,
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => $layout->district ?? $layout->village, 'addressCountry' => 'IN'],
            ] + ($layout->latitude ? ['geo' => ['@type' => 'GeoCoordinates', 'latitude' => (float) $layout->latitude, 'longitude' => (float) $layout->longitude]] : [])];

            return view('public.projects.show', [
                'tenant' => $tenantModel,
                'layout' => $layout,
                'plots' => $plots,
                'available' => $plots->where('status.value', 'available')->count(),
                'seo' => Seo::make(
                    Str::limit($layout->name.' – Plots in '.($layout->district ?? $layout->village ?? 'Tamil Nadu'), 50, ''),
                    Str::limit('See live plot availability at '.$layout->name.($place ? ', '.$place : '').'. Check size, facing and price, hold a plot online and our sales team will help you complete the purchase.', 158, ''),
                    $path,
                    breadcrumbs: [['Projects', '/projects'], [$layout->name, $path]],
                    schema: $schema,
                ),
            ]);
        });
    }
}
