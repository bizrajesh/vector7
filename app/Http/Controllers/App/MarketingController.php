<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SocialMetric;
use App\Models\SocialPost;
use App\Services\SocialService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * App marketing (AI): Claude writes Instagram / YouTube / Facebook posts for launched projects (or the platform itself),
 * posts publish on schedule through the official APIs, and a metrics dashboard shows reach, likes, comments and followers.
 * Platforms that are not connected keep posts as drafts marked "Not connected".
 */
class MarketingController extends Controller
{
    public function index(Request $request)
    {
        $posts = SocialPost::whereNull('tenant_id')->with('project:id,name,location,slug,tenant_id,status')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('platform'), fn ($q, $p) => $q->where('platform', $p))
            ->latest('id')->paginate(12)->withQueryString();
        $connected = collect(SocialService::PLATFORMS)->map(fn ($label, $key) => (bool) SocialService::account(null, $key));

        $metrics = SocialMetric::whereNull('tenant_id')->where('metric_date', '>=', today()->subDays(29))->orderBy('metric_date')->get();
        $latest = $metrics->groupBy('platform')->map->last();
        $colors = ['instagram' => '#B0397A', 'facebook' => '#1F4E9C', 'youtube' => '#B91C1C'];
        $chart = [
            'type' => 'line',
            'labels' => $metrics->pluck('metric_date')->unique()->map(fn ($d) => $d->format('d M'))->values(),
            'datasets' => $metrics->groupBy('platform')->map(fn ($rows, $p) => ['label' => SocialService::PLATFORMS[$p] ?? $p, 'data' => $rows->pluck('followers')->values(), 'borderColor' => $colors[$p] ?? '#0F8F84', 'backgroundColor' => $colors[$p] ?? '#0F8F84'])->values(),
        ];

        return view('app.marketing.index', [
            'posts' => $posts,
            'connected' => $connected,
            'latest' => $latest,
            'chart' => $chart,
            'projects' => Project::launched()->with('tenantRel:id,name')->orderBy('name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->name.' — '.$p->tenantRel?->name]),
        ]);
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'project_id' => 'required|integer',
            'platform' => ['required', Rule::in(array_keys(SocialService::PLATFORMS))],
            'brief' => 'nullable|string|max:500',
            'scheduled_at' => 'nullable|date|after:now',
        ]);
        $project = Project::launched()->with('plots')->findOrFail($data['project_id']);
        try {
            // App-level post: AI credits are charged to the platform, not the tenant.
            $text = app(Tenancy::class)->run(null, fn () => SocialService::write($project, $data['platform'], null, null, (string) ($data['brief'] ?? '')));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $connected = (bool) SocialService::account(null, $data['platform']);
        SocialPost::create($text + [
            'tenant_id' => null, 'project_id' => $project->id, 'platform' => $data['platform'],
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => ! empty($data['scheduled_at']) && $connected ? 'scheduled' : 'draft',
            'error' => $connected ? null : 'Not connected',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('ok', $connected ? 'Post written.' : 'Post written and kept as a draft — '.SocialService::PLATFORMS[$data['platform']].' is not connected (Settings → Integrations).');
    }

    public function update(Request $request, SocialPost $post)
    {
        abort_unless($post->tenant_id === null, 404);
        $data = $request->validate([
            'caption' => 'required|string|max:5000',
            'hashtags' => 'nullable|string|max:500',
            'scheduled_at' => 'nullable|date',
        ]);
        $connected = (bool) SocialService::account(null, $post->platform);
        $status = $post->status === 'published' ? 'published' : (! empty($data['scheduled_at']) && $connected ? 'scheduled' : 'draft');
        $post->update($data + ['status' => $status, 'error' => $connected ? null : 'Not connected']);

        return back()->with('ok', $status === 'scheduled' ? 'Post scheduled.' : 'Post saved.');
    }

    public function publish(SocialPost $post)
    {
        abort_unless($post->tenant_id === null && $post->status !== 'published', 404);

        return SocialService::publish($post->load('project'))
            ? back()->with('ok', 'Published to '.SocialService::PLATFORMS[$post->platform].'.')
            : back()->with('error', 'Not published: '.$post->fresh()->error);
    }

    public function destroy(SocialPost $post)
    {
        abort_unless($post->tenant_id === null, 404);
        $post->delete();

        return back()->with('ok', 'Post deleted.');
    }
}
