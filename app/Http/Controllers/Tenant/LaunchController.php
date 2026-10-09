<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\MatchRequirements;
use App\Models\Project;
use App\Models\StorageFile;
use App\Services\ClaudeClient;
use App\Services\FileStore;
use App\Services\PlotImporter;
use App\Services\ProjectService;
use App\Services\SeoService;
use App\Support\Format;
use Illuminate\Http\Request;

/**
 * Project launch. Only Tenant Admin / Manager can launch, upload the layout, import/edit plots and set prices/offers
 * (launch.create/update/approve). Sales & Support have launch.view only — read-only screens, and the server rejects writes.
 */
class LaunchController extends Controller
{
    public function index()
    {
        return view('ws.launch.index', [
            'ready' => Project::where('status', 'ready_to_launch')->withCount('plots')->orderBy('name')->get(),
            'launched' => Project::where('status', 'launched')->withCount(['plots', 'plots as available_count' => fn ($q) => $q->where('status', 'available'), 'plots as sold_count' => fn ($q) => $q->where('status', 'sold')])->orderByDesc('launched_at')->get(),
        ]);
    }

    public function show(Request $request, Project $project)
    {
        abort_unless(in_array($project->status, ['ready_to_launch', 'launched'], true), 404);
        $plots = $project->plots()->orderByRaw('CAST(plot_no AS UNSIGNED), plot_no')->get();
        $gallery = StorageFile::whereIn('id', $project->gallery ?? [])->get();

        return view('ws.launch.show', [
            'project' => $project->load('layoutFile', 'estimate'),
            'plots' => $plots,
            'gallery' => $gallery,
            'canEdit' => $request->user()->hasPerm('launch.update'),
            'canImport' => $request->user()->hasPerm('launch.create'),
            'canDelete' => $request->user()->hasPerm('launch.delete'),
            'canGoLive' => $request->user()->hasPerm('launch.approve'),
            'aiReady' => ClaudeClient::available(),
            'stats' => $plots->groupBy('status')->map->count(),
        ]);
    }

    /** Upload the layout picture (image or PDF). */
    public function layout(Request $request, Project $project)
    {
        $this->writable($project);
        $request->validate(['layout' => FileStore::rules('layout', 20480)]);
        $old = $project->layoutFile;
        $file = FileStore::store($request->file('layout'), 'layout', $project->tenant_id, $project, 'layout');
        $dims = str_starts_with($file->mime, 'image/') ? @getimagesize($request->file('layout')->getRealPath()) : null;
        $project->update(['layout_file_id' => $file->id, 'layout_width' => $dims[0] ?? null, 'layout_height' => $dims[1] ?? null]);
        if ($old) {
            FileStore::delete($old);
        }

        return back()->with('ok', 'Layout picture uploaded.');
    }

    public function details(Request $request, Project $project)
    {
        $this->writable($project);
        $data = $request->validate([
            'promo_text' => 'nullable|string|max:5000',
            'facilities_text' => 'nullable|string|max:2000',
            'map_url' => 'nullable|url|max:500',
            'is_featured' => 'nullable|boolean',
        ]);
        $project->update([
            'promo_text' => $data['promo_text'] ?? null,
            'facilities' => array_values(array_filter(array_map('trim', preg_split('/\r?\n|,/', (string) ($data['facilities_text'] ?? ''))))),
            'map_url' => $data['map_url'] ?? null,
            'is_featured' => $request->boolean('is_featured'),
        ]);

        return back()->with('ok', 'Promotion details saved.');
    }

    /** "Write with AI" for the promotion text. */
    public function aiText(Request $request, Project $project)
    {
        $this->writable($project);
        $facilities = implode(', ', $project->facilities ?? []) ?: $project->estimate?->lines->where('line_type', 'facility')->pluck('description')->implode(', ');
        $plots = $project->plots;
        $prompt = "Write an attractive, honest marketplace description (120–170 words, plain text, no headings, no emojis) for an approved residential plot layout in India.\n"
            ."Name: {$project->name}\nLocation: {$project->location}, {$project->district}, {$project->state}\nApproval: {$project->approval_type} approval\n"
            .'Size: '.(float) $project->size_acres." acres\nFacilities: {$facilities}\n"
            .($plots->count() ? 'Plots: '.$plots->count().' plots from '.Format::num($plots->min('size_sqft')).' to '.Format::num($plots->max('size_sqft')).' sq ft, starting at '.Format::inr($plots->min(fn ($p) => $p->currentPrice()))."\n" : '')
            .'Mention the approval, location advantages in general terms and the facilities. Do not invent distances, landmarks or legal claims.';
        try {
            $text = ClaudeClient::ask('promo_text', $prompt, null, [], 700, $project->tenantRel);
        } catch (\App\Exceptions\PlanLimitException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->withInput(['promo_text' => trim($text), 'facilities_text' => implode("\n", $project->facilities ?? []), 'map_url' => $project->map_url])->with('ok', 'AI draft added below — review and save it.');
    }

    public function gallery(Request $request, Project $project)
    {
        $this->writable($project);
        $request->validate(['images' => 'required|array|max:12', 'images.*' => FileStore::rules('image', 8192)]);
        $ids = $project->gallery ?? [];
        foreach ($request->file('images') as $img) {
            $ids[] = FileStore::store($img, 'gallery', $project->tenant_id, $project, 'image')->id;
        }
        $project->update(['gallery' => array_slice($ids, -20)]);

        return back()->with('ok', 'Photos added to the gallery.');
    }

    public function removeImage(Project $project, StorageFile $file)
    {
        $this->writable($project);
        abort_unless(in_array($file->id, $project->gallery ?? [], true), 404);
        $project->update(['gallery' => array_values(array_diff($project->gallery, [$file->id]))]);
        FileStore::delete($file);

        return back()->with('ok', 'Photo removed.');
    }

    /** Go live: project and plots appear in the marketplace; sellable % recalculated from actual plots. */
    public function goLive(Project $project)
    {
        if ($project->status !== 'ready_to_launch') {
            return back()->with('error', 'Only a Ready to Launch project can go live.');
        }
        if (! $project->layout_file_id) {
            return back()->with('error', 'Upload the layout picture first.');
        }
        if (! $project->plots()->exists()) {
            return back()->with('error', 'Load the plots first.');
        }
        $project->update(['launched_at' => now()]);
        ProjectService::setStatus($project, 'launched', 'Published on the vector7 marketplace.');
        if ($project->estimate) {
            ProjectService::recalcEstimate($project->estimate()->with('lines')->first(), $project);
        }
        SeoService::refreshProject($project);
        MatchRequirements::dispatch($project->id);

        return back()->with('ok', "{$project->name} is live on the marketplace.");
    }

    public function sample()
    {
        return response(PlotImporter::sampleCsv(), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="vector7-plots-sample.csv"']);
    }

    private function writable(Project $project): void
    {
        abort_unless(in_array($project->status, ['ready_to_launch', 'launched'], true), 404);
    }
}
