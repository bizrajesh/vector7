<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\SeoMeta;
use App\Services\AppSettings;
use App\Services\AuditLogger;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** SEO settings: defaults, AI-crawler decision (written into robots.txt), per-page meta, and "Build now" (= php artisan seo:build). */
class SeoController extends Controller
{
    public function index(Request $request)
    {
        return view('app.seo.index', [
            'meta' => SeoMeta::when($request->query('type'), fn ($q, $t) => $q->where('page_type', $t))
                ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('page_key', 'like', "%$s%")->orWhere('title', 'like', "%$s%")))
                ->orderBy('page_type')->orderBy('page_key')->paginate(25)->withQueryString(),
            'settings' => [
                'default_title' => AppSettings::get('seo.default_title'),
                'default_description' => AppSettings::get('seo.default_description'),
                'ai_crawlers' => AppSettings::get('seo.ai_crawlers', 'allow'),
            ],
            'robots' => SeoService::robots(),
            'indexable' => config('seo.indexable'),
            'bots' => config('seo.ai_crawlers'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'default_title' => 'required|string|max:60',
            'default_description' => 'required|string|max:155',
            'ai_crawlers' => 'required|in:allow,block',
        ]);
        foreach ($data as $k => $v) {
            AppSettings::set("seo.$k", $v);
        }
        AuditLogger::log('seo_settings_updated', null, null, $data);
        Cache::forget('sitemap.xml');

        return back()->with('ok', 'SEO settings saved. robots.txt now '.($data['ai_crawlers'] === 'block' ? 'blocks' : 'allows').' AI crawlers.');
    }

    public function build(Request $request)
    {
        $r = SeoService::buildAll($request->boolean('ai'));

        return back()->with('ok', "SEO rebuilt: {$r['meta']} page(s) refreshed, {$r['projects']} launched project(s), {$r['sitemap_urls']} URLs in the sitemap.");
    }

    public function meta(Request $request, SeoMeta $meta)
    {
        $data = $request->validate([
            'title' => 'required|string|max:60',
            'description' => 'required|string|max:155',
            'is_manual' => 'boolean',
        ]);
        $meta->update($data + ['is_manual' => $request->boolean('is_manual', true)]);

        return back()->with('ok', 'Meta saved'.($meta->is_manual ? ' and locked against automatic rebuilds.' : '.'));
    }
}
