<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** SEO standard §11 gates for public routes. */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    public static function routes(): array
    {
        return [['/'], ['/features'], ['/pricing'], ['/about'], ['/contact'], ['/privacy'], ['/terms']];
    }

    /** @dataProvider routes */
    public function test_public_page_meta(string $uri): void
    {
        $html = $this->get($uri)->assertOk()->getContent();

        preg_match('/<title>(.*?)<\/title>/s', $html, $title);
        preg_match('/<meta name="description" content="([^"]*)"/', $html, $description);

        $this->assertNotEmpty($title[1] ?? null);
        $this->assertLessThanOrEqual(70, mb_strlen(html_entity_decode($title[1])));
        $this->assertGreaterThanOrEqual(120, mb_strlen(html_entity_decode($description[1] ?? '')));
        $this->assertLessThanOrEqual(170, mb_strlen(html_entity_decode($description[1] ?? '')));
        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('og:image', $html);
        $this->assertStringContainsString('application/ld+json', $html);
    }

    public function test_robots_blocks_everything_outside_production(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
    }
}
