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
    /** Statuses of the project phase (approvals, estimate, stage tracking) that "Skip to launch" can jump over. */
    public const PHASE_STATUSES = ['draft', 'init', 'go_no_go', 'in_progress'];

    /**
     * Skip the project phase: an existing project goes straight to Ready to Launch with its approval details,
     * then the normal launch screen takes the layout, plots, photos and contact before Go live.
     * Stage tracking and the estimate are kept as they are (nothing is deleted).
     */
    public function skip(Request $request, Project $project)
    {
        abort_unless(in_array($project->status, self::PHASE_STATUSES, true), 404);
        $data = $request->validate([
            'approval_type' => ['required', \Illuminate\Validation\Rule::in(Project::APPROVAL_TYPES)],
            'approval_authority' => 'nullable|string|max:60',
            'approval_number' => 'nullable|string|max:60',
            'reason' => 'nullable|string|max:255',
        ]);
        $from = $project->statusLabel();
        $project->update(['approval_type' => $data['approval_type'], 'approval_authority' => $data['approval_authority'] ?? $project->approval_authority,
            'approval_number' => $data['approval_number'] ?? $project->approval_number]);
        ProjectService::setStatus($project, 'ready_to_launch', 'Project phase skipped (was '.$from.') — going straight to launch.'.(! empty($data['reason']) ? ' Reason: '.$data['reason'] : ''));
        \App\Services\AuditLogger::log('project_phase_skipped', $project, ['status' => $from], ['status' => 'ready_to_launch', 'reason' => $data['reason'] ?? null], $project->tenant_id);

        return redirect()->route('ws.launch.show', $project)->with('ok', "{$project->name} is Ready to Launch. Upload the layout, load the plots, then press Go live.");
    }

    public function index()
    {
        return view('ws.launch.index', [
            'ready' => Project::where('status', 'ready_to_launch')->withCount('plots')->orderBy('name')->get(),
            'inPhase' => Project::whereIn('status', self::PHASE_STATUSES)->orderBy('name')->get(),
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
            'features' => \App\Models\FeatureMaster::forType('layout')->get()->groupBy('group'),
            'selectedFeatures' => $project->featureRows->whereNotNull('feature_master_id')->pluck('feature_master_id')->all(),
            'highlights' => $project->featureRows->whereNotNull('custom_text')->pluck('custom_text')->values()->all(),
            'contact' => ($c = $project->contactBlock) ? ['contact_name' => $c->name, 'contact_mobile' => $c->mobile, 'contact_whatsapp' => $c->whatsapp,
                'contact_email' => $c->email, 'allow_call' => $c->allow_call, 'allow_whatsapp' => $c->allow_whatsapp, 'allow_email' => $c->allow_email]
                : \App\Services\PropertyDetails::contactDefaults($project->tenantRel),
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

    /** Approval badge, map pin, features and contact block for the marketplace (spec 17.1 / 17.4 / 17.5). */
    public function marketplace(Request $request, Project $project)
    {
        $this->writable($project);
        $data = $request->validate([
            'approval_authority' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(QuickLaunchController::AUTHORITIES))],
            'approval_number' => 'nullable|string|max:60',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ] + \App\Services\PropertyDetails::featureRules() + \App\Services\PropertyDetails::contactRules(), \App\Services\PropertyDetails::contactMessages());
        $contact = \App\Services\PropertyDetails::contactFromRequest($request, array_intersect_key($data, \App\Services\PropertyDetails::contactRules()));
        if (! \App\Services\PropertyDetails::hasMode($contact)) {
            return back()->withInput()->withErrors(['allow_call' => 'Choose at least one mode of contact.']);
        }
        $project->update(array_intersect_key($data, array_flip(['approval_authority', 'approval_number', 'lat', 'lng'])));
        \App\Services\PropertyDetails::syncFeatures($project, (array) ($data['features'] ?? []), (array) ($data['highlights'] ?? []), 'layout');
        \App\Services\PropertyDetails::saveContact($project, $contact);

        return back()->with('ok', 'Marketplace details saved.');
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

    /** Older "Add photos" endpoint — now goes through the shared media pipeline (WebP, thumbnails, EXIF removed). */
    public function gallery(Request $request, Project $project)
    {
        $this->writable($project);
        $request->validate(['images' => 'required|array|max:'.\App\Services\PropertyMedia::MAX_IMAGES, 'images.*' => \App\Services\PropertyMedia::imageRules()]);
        \App\Services\PropertyMedia::addImages($project, $request->file('images'));

        return back()->with('ok', 'Photos added.');
    }

    public function removeImage(Project $project, int $file)
    {
        $this->writable($project);
        \App\Services\PropertyMedia::delete($project->images()->where(fn ($q) => $q->whereKey($file)->orWhere('file_id', $file))->firstOrFail());

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
