<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Help knowledge base: Markdown files in resources/help with a small front matter block:
 *   ---
 *   title: Leads
 *   audience: tenant        (tenant | app | all)
 *   route: ws.leads.index   (optional "Open this screen" link)
 *   keywords: crm, follow-up
 *   ---
 * Used by the Help pages and by the workspace assistant (spec 17.10).
 */
class HelpCenter
{
    /** @return array<string, array{slug:string, title:string, audience:string, route:?string, keywords:string, body:string}> */
    public static function articles(): array
    {
        return Cache::remember('help.articles.'.self::version(), 3600, function () {
            $out = [];
            foreach (glob(resource_path('help/*.md')) ?: [] as $file) {
                $raw = (string) file_get_contents($file);
                $meta = [];
                if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $raw, $m)) {
                    foreach (explode("\n", $m[1]) as $line) {
                        if (str_contains($line, ':')) {
                            [$k, $v] = array_map('trim', explode(':', $line, 2));
                            $meta[$k] = $v;
                        }
                    }
                    $raw = $m[2];
                }
                $slug = basename($file, '.md');
                $out[$slug] = ['slug' => $slug, 'title' => $meta['title'] ?? Str::headline($slug), 'audience' => $meta['audience'] ?? 'all',
                    'route' => $meta['route'] ?? null, 'keywords' => $meta['keywords'] ?? '', 'body' => trim($raw)];
            }
            ksort($out);

            return $out;
        });
    }

    private static function version(): string
    {
        return (string) collect(glob(resource_path('help/*.md')) ?: [])->map(fn ($f) => filemtime($f))->max();
    }

    public static function find(string $slug): ?array
    {
        return self::articles()[$slug] ?? null;
    }

    public static function forUser(?User $user, array $a): bool
    {
        if (! $user) {
            return true;
        }

        return $a['audience'] === 'all' || ($a['audience'] === 'app') === $user->isAppUser();
    }

    public static function all(?User $user = null): array
    {
        return array_filter(self::articles(), fn ($a) => self::forUser($user, $a));
    }

    /** Simple keyword scoring over title, keywords and body. */
    public static function search(string $q, ?User $user = null, int $limit = 5): array
    {
        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', Str::lower($q)))->filter(fn ($w) => mb_strlen($w) > 2)->reject(fn ($w) => in_array($w, ['how', 'the', 'and', 'can', 'what', 'where', 'for', 'add', 'does', 'with', 'from'], true))->values();
        if ($words->isEmpty()) {
            return [];
        }
        $scored = [];
        foreach (self::all($user) as $a) {
            $title = Str::lower($a['title'].' '.$a['keywords']);
            $body = Str::lower($a['body']);
            $score = 0;
            foreach ($words as $w) {
                $score += substr_count($title, $w) * 5 + min(5, substr_count($body, $w));
            }
            if ($score > 0) {
                $scored[] = $a + ['score' => $score];
            }
        }
        usort($scored, fn ($x, $y) => $y['score'] <=> $x['score']);

        return array_slice($scored, 0, $limit);
    }

    public static function html(array $article): string
    {
        return Str::markdown($article['body'], ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    public static function url(array $article): string
    {
        return route('help.show', $article['slug']);
    }

    public static function screenUrl(array $article): ?string
    {
        try {
            return $article['route'] ? route($article['route']) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
