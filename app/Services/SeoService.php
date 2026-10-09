<?php

namespace App\Services;

use App\Models\Plot;
use App\Models\Project;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Support\Format;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * SEO per the attached guideline: unique titles (≤ 60) and descriptions (≤ 155), canonical URLs,
 * Open Graph / Twitter, JSON-LD (Organization, WebSite, BreadcrumbList, Product/Offer, RealEstateListing,
 * SoftwareApplication), environment-aware robots.txt and an XML sitemap with real last-modified dates.
 */
class SeoService
{
    public static function title(string $t): string
    {
        return Str::limit(trim($t), 60, '');
    }

    public static function description(string $d): string
    {
        $d = trim(preg_replace('/\s+/', ' ', strip_tags($d)));

        return mb_strlen($d) > 155 ? rtrim(mb_substr($d, 0, 152)).'…' : $d;
    }

    /** Stored (editable) meta for a page, falling back to generated defaults. */
    public static function meta(string $type, string $key, string $title, string $description): array
    {
        $m = SeoMeta::where('page_type', $type)->where('page_key', $key)->first();

        return [
            'title' => self::title($m?->title ?: $title),
            'description' => self::description($m?->description ?: $description),
        ];
    }

    public static function projectDefaults(Project $p): array
    {
        $plots = $p->plots->where('status', 'available');
        $from = $plots->isNotEmpty() ? Format::inrShort($plots->min(fn ($x) => $x->currentPrice())) : null;

        return [
            'title' => "{$p->name} – Plots in {$p->location}",
            'description' => "{$p->approval_type}-approved residential plots at {$p->name}, {$p->location}, {$p->district}. "
                .($plots->count() ? $plots->count().' plots available'.($from ? ", from $from" : '').'. ' : '')
                .'See the live plot map, offers and book online on vector7.',
        ];
    }

    public static function plotDefaults(Plot $plot): array
    {
        $p = $plot->project;

        return [
            'title' => "Plot {$plot->plot_no}, {$p->name} – ".Format::num($plot->size_sqft).' sq ft',
            'description' => Format::num($plot->size_sqft)." sq ft {$plot->facing}-facing plot {$plot->plot_no} in {$p->name}, {$p->location}. "
                .'Price '.Format::inr($plot->currentPrice()).($plot->offerIsActive() ? ' (offer till '.$plot->offer_valid_till->format('d-m-Y').')' : '').'. Patta '.$plot->patta_number.'. Book on vector7.',
        ];
    }

    /** Regenerate meta for a project and its plots (manual edits are kept). Optionally Claude-assisted. */
    public static function refreshProject(Project $project, bool $ai = false): int
    {
        return app(Tenancy::class)->withoutScope(function () use ($project, $ai) {
            $project->loadMissing('plots');
            $n = 0;
            $d = self::projectDefaults($project);
            if ($ai && ClaudeClient::available()) {
                try {
                    $reply = ClaudeClient::ask('seo_meta', "Write an SEO title (max 60 characters) and meta description (max 155 characters) for this Indian residential plot project page. Return JSON {\"title\":…,\"description\":…}.\n"
                        ."Facts: {$d['description']} Promotion: ".Str::limit((string) $project->promo_text, 600), null, [], 300);
                    $j = ClaudeClient::json($reply);
                    if (is_array($j) && ! empty($j['title']) && ! empty($j['description'])) {
                        $d = ['title' => (string) $j['title'], 'description' => (string) $j['description']];
                    }
                } catch (\Throwable) {
                    // keep the generated defaults
                }
            }
            $n += self::upsert('project', $project->slug, $d);
            foreach ($project->plots->where('status', 'available') as $plot) {
                $plot->setRelation('project', $project);
                $n += self::upsert('plot', $project->slug.'/'.$plot->plot_no, self::plotDefaults($plot));
            }
            Cache::forget('sitemap.xml');

            return $n;
        });
    }

    private static function upsert(string $type, string $key, array $d): int
    {
        $m = SeoMeta::firstOrNew(['page_type' => $type, 'page_key' => $key]);
        if ($m->is_manual) {
            return 0;
        }
        $m->fill(['title' => self::title($d['title']), 'description' => self::description($d['description'])])->save();

        return 1;
    }

    /** php artisan seo:build */
    public static function buildAll(bool $ai = false): array
    {
        $count = 0;
        $projects = app(Tenancy::class)->withoutScope(fn () => Project::launched()->with('plots')->get());
        foreach ($projects as $p) {
            $count += self::refreshProject($p, $ai);
        }
        foreach (['home' => ['vector7 – Approved residential plots', AppSettings::get('seo.default_description')],
            'projects' => ['Approved plot layouts for sale', 'Search DTCP and panchayat approved residential plots by location, budget and size. Live availability and offers.'],
            'services' => ['Property services – legal, survey, loans', 'Legal title opinion, land survey, registration assistance, home-loan help and marketing for promoters.'],
            'pricing' => ['vector7 for layout promoters – Plans', 'Run approvals, estimates, plot launch, bookings, payments and registration in one portal. Start a free trial.'],
            'about' => ['About vector7', 'vector7 is a realty manage portal connecting plot buyers with trusted layout promoters.'],
            'support' => ['Support & contact', 'Get help with your plot booking, payments, documents or your promoter workspace.'],
        ] as $key => [$t, $d]) {
            $m = SeoMeta::firstOrNew(['page_type' => 'page', 'page_key' => $key]);
            if (! $m->is_manual) {
                $m->fill(['title' => self::title($t), 'description' => self::description((string) $d)])->save();
                $count++;
            }
        }
        Cache::forget('sitemap.xml');
        $xml = self::sitemap();

        return ['meta' => $count, 'projects' => $projects->count(), 'sitemap_urls' => substr_count($xml, '<url>')];
    }

    public static function sitemap(): string
    {
        return Cache::remember('sitemap.xml', 3600, function () {
            $urls = [];
            $add = function (string $loc, $lastmod = null, string $freq = 'weekly', string $prio = '0.5') use (&$urls) {
                $urls[] = '<url><loc>'.e($loc).'</loc>'.($lastmod ? '<lastmod>'.$lastmod->toAtomString().'</lastmod>' : '').'<changefreq>'.$freq.'</changefreq><priority>'.$prio.'</priority></url>';
            };
            $latest = app(Tenancy::class)->withoutScope(fn () => Project::launched()->max('updated_at'));
            $add(route('home'), $latest ? \Carbon\Carbon::parse($latest) : null, 'daily', '1.0');
            $add(route('market.projects'), $latest ? \Carbon\Carbon::parse($latest) : null, 'daily', '0.9');
            foreach (['market.services', 'market.about', 'market.support', 'market.pricing', 'market.privacy', 'market.terms'] as $r) {
                $add(route($r), null, 'monthly', '0.4');
            }
            foreach (Service::where('is_active', true)->get() as $s) {
                $add(route('market.service', $s->slug), $s->updated_at, 'monthly', '0.4');
            }
            app(Tenancy::class)->withoutScope(function () use ($add) {
                foreach (Project::launched()->with(['plots' => fn ($q) => $q->where('status', 'available')])->get() as $p) {
                    $lastPlot = $p->plots->max('updated_at');
                    $add($p->publicUrl(), max($p->updated_at, $lastPlot ?? $p->updated_at), 'daily', '0.8');
                    foreach ($p->plots as $plot) {
                        $plot->setRelation('project', $p);
                        $add($plot->publicUrl(), $plot->updated_at, 'weekly', '0.6');
                    }
                }
            });

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.implode('', $urls).'</urlset>';
        });
    }

    public static function robots(): string
    {
        if (! config('seo.indexable')) {
            return "User-agent: *\nDisallow: /\n";
        }
        $lines = ["User-agent: *", 'Disallow: /workspace', 'Disallow: /app', 'Disallow: /account', 'Disallow: /files', 'Disallow: /profile', 'Disallow: /login', 'Disallow: /register', 'Disallow: /*?*sort=', 'Allow: /'];
        if (AppSettings::get('seo.ai_crawlers', 'allow') === 'block') {
            foreach (config('seo.ai_crawlers') as $bot) {
                $lines[] = '';
                $lines[] = "User-agent: $bot";
                $lines[] = 'Disallow: /';
            }
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return implode("\n", $lines)."\n";
    }

    // ---------- JSON-LD ----------
    public static function organization(): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => AppSettings::get('org.name', 'vector7'),
            'url' => url('/'),
            'logo' => asset('images/brand/logo-light.png'),
            'email' => AppSettings::get('org.support_email'),
            'sameAs' => array_values(array_filter([AppSettings::get('org.instagram'), AppSettings::get('org.facebook'), AppSettings::get('org.youtube'), AppSettings::get('org.linkedin')])),
        ]);
    }

    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'vector7',
            'url' => url('/'),
            'potentialAction' => ['@type' => 'SearchAction', 'target' => route('market.projects').'?q={search_term_string}', 'query-input' => 'required name=search_term_string'],
        ];
    }

    /** @param array<int, array{0:string,1:string}> $items [name, url] */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($it, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $it[0], 'item' => $it[1]], $items, array_keys($items)),
        ];
    }

    public static function projectListing(Project $p): array
    {
        $available = $p->plots->where('status', 'available');
        $prices = $available->map(fn ($x) => $x->currentPrice());

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'RealEstateListing',
            'name' => $p->name,
            'url' => $p->publicUrl(),
            'description' => Str::limit((string) $p->promo_text, 300),
            'datePosted' => $p->launched_at?->toDateString(),
            'image' => $p->layoutUrl(),
            'about' => ['@type' => 'Place', 'name' => $p->name, 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $p->location, 'addressRegion' => $p->state, 'postalCode' => $p->pin, 'addressCountry' => 'IN']],
            'offers' => $prices->isNotEmpty() ? ['@type' => 'AggregateOffer', 'priceCurrency' => 'INR', 'lowPrice' => round($prices->min()), 'highPrice' => round($prices->max()), 'offerCount' => $prices->count(), 'availability' => 'https://schema.org/InStock'] : null,
        ]);
    }

    public static function plotProduct(Plot $plot): array
    {
        $p = $plot->project;
        $offer = ['@type' => 'Offer', 'priceCurrency' => 'INR', 'price' => round($plot->currentPrice()), 'url' => $plot->publicUrl(),
            'availability' => $plot->status === 'available' ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut'];
        if ($plot->offerIsActive()) {
            $offer['priceValidUntil'] = $plot->offer_valid_till->toDateString();
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => "Plot {$plot->plot_no}, {$p->name}",
            'description' => self::plotDefaults($plot)['description'],
            'category' => 'Residential plot',
            'brand' => ['@type' => 'Brand', 'name' => $p->tenantRel?->name ?? 'vector7'],
            'additionalProperty' => [
                ['@type' => 'PropertyValue', 'name' => 'Area', 'value' => (float) $plot->size_sqft, 'unitText' => 'sq ft'],
                ['@type' => 'PropertyValue', 'name' => 'Facing', 'value' => $plot->facing],
                ['@type' => 'PropertyValue', 'name' => 'Patta number', 'value' => $plot->patta_number],
            ],
            'offers' => $offer,
        ];
    }

    public static function softwareApplication(): array
    {
        $plans = \App\Models\Plan::where('is_active', true)->orderBy('price')->get();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'vector7 — Realty Manage Portal',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'offers' => ['@type' => 'AggregateOffer', 'priceCurrency' => 'INR', 'lowPrice' => (float) ($plans->min('price') ?? 0), 'highPrice' => (float) ($plans->max('price') ?? 0), 'offerCount' => $plans->count()],
        ];
    }
}
