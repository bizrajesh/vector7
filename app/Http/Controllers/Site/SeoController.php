<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\PublicCatalog;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Environment-aware robots.txt and a sitemap of canonical, indexable URLs only (SEO standard §8).
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $body = app()->isProduction()
            ? implode("\n", [
                'User-agent: *',
                'Disallow: /app/',
                'Disallow: /platform/',
                'Disallow: /portal/',
                'Disallow: /login',
                'Disallow: /register',
                'Disallow: /webhooks/',
                'Disallow: /book/',
                'Disallow: /track',
                'Allow: /',
                '',
                'Sitemap: '.url('/sitemap.xml'),
            ])
            : "User-agent: *\nDisallow: /";

        return response($body."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(PublicCatalog $catalog): Response
    {
        $lastmod = date('Y-m-d', filemtime(resource_path('views/public')) ?: time());
        $pages = collect(['/', '/features', '/pricing', '/projects', '/about', '/contact', '/privacy', '/terms'])
            ->map(fn ($path) => [$path, $lastmod]);

        foreach ($catalog->layouts(5000) as $layout) {
            $pages->push(['/projects/'.$layout->tenant->slug.'/'.Str::lower($layout->code), $layout->updated_at->toDateString()]);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($pages as [$path, $date]) {
            $xml .= '  <url><loc>'.e($path === '/' ? rtrim(url('/'), '/') : url($path)).'</loc><lastmod>'.$date.'</lastmod></url>'."\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
