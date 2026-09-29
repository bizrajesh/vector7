<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * SeoData for the shared <x-seo-head> component (SEO standard §4–5).
 */
final class Seo
{
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public string $robots,
        public array $schema,
        public string $ogImage,
    ) {}

    public static function make(string $title, string $description, string $path, array $breadcrumbs = [], array $schema = []): self
    {
        $canonical = $path === '/' ? rtrim(url('/'), '/') : url($path);

        if ($breadcrumbs) {
            $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')]];
            foreach ($breadcrumbs as $i => [$name, $crumbPath]) {
                $items[] = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => $name, 'item' => url($crumbPath)];
            }
            $schema[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
        }

        return new self(
            $title.' · Vector7',
            $description,
            $canonical,
            app()->isProduction() ? 'index, follow' : 'noindex, nofollow',
            $schema,
            url('/img/og-default.png'),
        );
    }

    public static function organization(): array
    {
        return [
            '@context' => 'https://schema.org', '@type' => 'Organization', '@id' => url('/').'#org',
            'name' => config('app.name'), 'url' => url('/'), 'logo' => url('/img/logo-512.png'),
            'contactPoint' => ['@type' => 'ContactPoint', 'email' => config('vector7.support_email'), 'contactType' => 'customer support', 'areaServed' => 'IN'],
        ];
    }

    public static function website(): array
    {
        return ['@context' => 'https://schema.org', '@type' => 'WebSite', '@id' => url('/').'#website', 'url' => url('/'), 'name' => config('app.name'), 'publisher' => ['@id' => url('/').'#org']];
    }

    public static function softwareApplication(): array
    {
        return ['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => config('app.name'), 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'url' => url('/')];
    }

    public static function offers(Collection $plans): array
    {
        return [
            '@context' => 'https://schema.org', '@type' => 'Product', 'name' => config('app.name').' subscription',
            'description' => 'Real-estate layout and plot sales management software',
            'offers' => $plans->map(fn ($p) => [
                '@type' => 'Offer', 'name' => $p->name, 'price' => (string) $p->price_monthly, 'priceCurrency' => 'INR', 'url' => url('/pricing'),
            ])->values()->all(),
        ];
    }

    /** JSON for <script type="application/ld+json"> with "<" escaped so it cannot break out of the tag. */
    public function jsonLd(): array
    {
        return array_map(fn ($s) => json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP), $this->schema);
    }
}
