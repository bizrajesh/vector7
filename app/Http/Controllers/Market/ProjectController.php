<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Models\Plot;
use App\Models\Project;
use App\Models\StorageFile;
use App\Services\FileStore;
use App\Services\SeoService;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Public project & plot pages. Only launched projects; sold plots leave the showcase. */
class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $q = Project::launched()->with(['plots', 'layoutFile'])
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('location', 'like', "%$s%")->orWhere('district', 'like', "%$s%")))
            ->when($request->query('location'), fn ($q, $l) => $q->where(fn ($w) => $w->where('location', $l)->orWhere('district', $l)))
            ->when($request->query('type'), fn ($q, $t) => $q->where('approval_type', $t));
        $projects = $q->orderByDesc('is_featured')->orderByDesc('launched_at')->get();
        $budget = (float) $request->query('budget');
        $size = (float) $request->query('size');
        $projects = $projects->filter(function (Project $p) use ($budget, $size) {
            $avail = $p->plots->where('status', 'available');
            if ($budget > 0) {
                $avail = $avail->filter(fn ($x) => $x->currentPrice() <= $budget);
            }
            if ($size > 0) {
                $avail = $avail->filter(fn ($x) => $x->size_sqft >= $size * 0.85 && $x->size_sqft <= $size * 1.15);
            }

            return $avail->isNotEmpty() || ($budget <= 0 && $size <= 0);
        })->values();
        $seo = SeoService::meta('page', 'projects', 'Approved plot layouts for sale', 'Search DTCP and panchayat approved residential plots by location, budget and size. Live availability and offers.');

        return view('market.projects', [
            'projects' => $projects,
            'locations' => Project::launched()->distinct()->orderBy('location')->pluck('location'),
            // filter/sort parameters canonicalise to the clean URL
            'seo' => $seo + ['canonical' => route('market.projects')],
            'jsonld' => [SeoService::breadcrumbs([['Home', route('home')], ['Projects', route('market.projects')]])],
        ]);
    }

    public function show(string $location, string $slug)
    {
        $project = $this->find($slug);
        if ($location !== $project->locationSlug()) {
            return redirect($project->publicUrl(), 301);
        }
        $project->load(['plots' => fn ($q) => $q->where('status', '!=', 'sold')->orderByRaw('CAST(plot_no AS UNSIGNED), plot_no'), 'layoutFile', 'tenantRel', 'stages', 'estimate']);
        $project->plots->each->setRelation('project', $project);
        $gallery = StorageFile::whereIn('id', $project->gallery ?? [])->get();
        $d = SeoService::projectDefaults($project);

        return view('market.project', [
            'project' => $project,
            'tenant' => $project->tenantRel,
            'plots' => $project->plots,
            'gallery' => $gallery,
            'seo' => SeoService::meta('project', $project->slug, $d['title'], $d['description']) + ['canonical' => $project->publicUrl(), 'image' => $project->layoutUrl(), 'type' => 'website'],
            'jsonld' => [
                SeoService::breadcrumbs([['Home', route('home')], ['Projects', route('market.projects')], [$project->name, $project->publicUrl()]]),
                SeoService::projectListing($project),
            ],
        ]);
    }

    /** /projects/{slug} → 301 to the canonical /projects/{location}/{slug} */
    public function legacy(string $slug)
    {
        return redirect($this->find($slug)->publicUrl(), 301);
    }

    public function plot(string $slug, string $plotNo)
    {
        $project = $this->find($slug);
        if ($plotNo !== strtolower($plotNo)) {
            return redirect(route('market.plot', ['project' => $slug, 'plotNo' => strtolower($plotNo)]), 301);
        }
        $plot = $project->plots()->whereRaw('LOWER(plot_no) = ?', [$plotNo])->firstOrFail();
        $plot->setRelation('project', $project);
        if ($plot->status === 'sold') {
            return redirect($project->publicUrl(), 301);
        }
        $d = SeoService::plotDefaults($plot);

        return view('market.plot', [
            'project' => $project->load('tenantRel', 'layoutFile'),
            'plot' => $plot,
            'gallery' => StorageFile::whereIn('id', $project->gallery ?? [])->get(),
            'similar' => $project->plots()->where('status', 'available')->where('id', '!=', $plot->id)->orderByRaw('ABS(size_sqft - ?)', [$plot->size_sqft])->take(4)->get()->each->setRelation('project', $project),
            'seo' => SeoService::meta('plot', $project->slug.'/'.$plot->plot_no, $d['title'], $d['description']) + ['canonical' => $plot->publicUrl(), 'image' => $project->layoutUrl()],
            'jsonld' => [
                SeoService::breadcrumbs([['Home', route('home')], ['Projects', route('market.projects')], [$project->name, $project->publicUrl()], ['Plot '.$plot->plot_no, $plot->publicUrl()]]),
                SeoService::plotProduct($plot),
            ],
        ]);
    }

    /** Layout picture of a launched project (served as WebP when possible, long cache). */
    public function layout(string $slug)
    {
        $project = $this->find($slug);
        $file = $project->layoutFile ?? abort(404);

        return $this->image($file);
    }

    public function gallery(string $slug, StorageFile $file)
    {
        $project = $this->find($slug);
        abort_unless(in_array($file->id, $project->gallery ?? [], true), 404);

        return $this->image($file);
    }

    private function image(StorageFile $file)
    {
        if (! str_starts_with($file->mime, 'image/')) {
            return response(FileStore::contents($file), 200, ['Content-Type' => $file->mime, 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
        }
        $key = 'webp:'.$file->id.':'.$file->updated_at?->timestamp;
        $webp = function_exists('imagewebp') ? Cache::remember($key, 86400 * 30, function () use ($file) {
            $im = @imagecreatefromstring(FileStore::contents($file));
            if (! $im) {
                return null;
            }
            imagepalettetotruecolor($im);
            ob_start();
            imagewebp($im, null, 82);
            imagedestroy($im);

            return base64_encode((string) ob_get_clean());
        }) : null;
        $body = $webp ? base64_decode($webp) : FileStore::contents($file);

        return response($body, 200, [
            'Content-Type' => $webp ? 'image/webp' : $file->mime,
            'Cache-Control' => 'public, max-age=2592000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function find(string $slug): Project
    {
        return Project::launched()->where('slug', Str::lower($slug))->firstOrFail();
    }
}
