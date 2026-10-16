<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\MatchRequirements;
use App\Models\FeatureMaster;
use App\Models\Project;
use App\Services\FileStore;
use App\Services\IdGenerator;
use App\Services\PlanLimiter;
use App\Services\PlotImporter;
use App\Services\ProjectService;
use App\Services\PropertyDetails;
use App\Services\PropertyMedia;
use App\Services\SeoService;
use App\Support\Excel;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Quick Launch Layout (spec 17.1): promoters with an already-approved layout publish it in one screen — no estimate,
 * Go/No-Go or stage tracking. Creates a project with launch_mode = direct and status Launched, so booking, sales,
 * payments, registration, accounts, reports and dashboards work exactly as for a normal project.
 */
class QuickLaunchController extends Controller
{
    public const AUTHORITIES = ['DTCP' => 'DTCP', 'BDO' => 'BDO / Panchayat Union', 'CMDA' => 'CMDA', 'LPA' => 'Local Planning Authority', 'Municipality' => 'Municipality / Corporation', 'RERA' => 'TNRERA'];

    public function create()
    {
        $tenant = app(Tenancy::class)->get();

        return view('ws.quick-launch.create', [
            'features' => FeatureMaster::forType('layout')->get()->groupBy('group'),
            'contact' => PropertyDetails::contactDefaults($tenant),
            'authorities' => self::AUTHORITIES,
            'maxImages' => PropertyMedia::MAX_IMAGES,
        ]);
    }

    public function store(Request $request)
    {
        $tenant = app(Tenancy::class)->get();
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'location' => 'required|string|max:100',
            'village' => 'nullable|string|max:100',
            'district' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pin' => ['required', 'regex:/^\d{6}$/'],
            'address' => 'nullable|string|max:255',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'approval_type' => ['required', Rule::in(Project::APPROVAL_TYPES)],
            'approval_authority' => ['nullable', Rule::in(array_keys(self::AUTHORITIES))],
            'approval_number' => 'nullable|string|max:60',
            'survey_numbers' => 'required|string|max:255',
            'patta_numbers' => 'required|string|max:255',
            'size_acres' => 'required|numeric|min:0.01|max:100000',
            'promo_text' => 'required|string|max:5000',
            'youtube_url' => 'nullable|url|max:255',
            'layout' => FileStore::rules('layout', 20480),
            'images' => ['required', 'array', 'min:'.PropertyMedia::MIN_IMAGES, 'max:'.PropertyMedia::MAX_IMAGES],
            'images.*' => PropertyMedia::imageRules(),
            'brochure' => ['nullable', 'file', 'max:10240', 'extensions:pdf', 'mimetypes:application/pdf'],
            'plots_csv' => 'nullable|string|max:500000',
            'plots_file' => FileStore::rules('import', 10240, false),
        ] + PropertyDetails::featureRules() + PropertyDetails::contactRules(), [
            'pin.regex' => 'PIN must be 6 digits.',
            'images.min' => 'Add at least '.PropertyMedia::MIN_IMAGES.' photos of the layout.',
        ] + PropertyDetails::contactMessages());
        $contact = PropertyDetails::contactFromRequest($request, array_intersect_key($data, PropertyDetails::contactRules()));
        if (! PropertyDetails::hasMode($contact)) {
            throw ValidationException::withMessages(['allow_call' => 'Choose at least one mode of contact.']);
        }
        if ($data['youtube_url'] ?? null) {
            $data['youtube_url'] = PropertyMedia::youtube($data['youtube_url']) ?? throw ValidationException::withMessages(['youtube_url' => 'Enter a YouTube video link.']);
        }
        PlanLimiter::ensure($tenant, 'projects');

        // Plots: same columns and validation as the normal launch import (spec 7.4 Step 3).
        if ($request->hasFile('plots_file')) {
            $parsed = PlotImporter::readUpload($request->file('plots_file')->getRealPath(), $request->file('plots_file')->getClientOriginalExtension());
        } elseif (trim((string) ($data['plots_csv'] ?? '')) !== '') {
            $parsed = PlotImporter::fromTable(Excel::parseCsvText($data['plots_csv']));
        } else {
            throw ValidationException::withMessages(['plots_csv' => 'Add the plots: paste the CSV or choose a CSV / Excel file.']);
        }
        if ($parsed['header_error']) {
            throw ValidationException::withMessages(['plots_csv' => $parsed['header_error']]);
        }
        $draft = new Project(['tenant_id' => $tenant->id, 'size_acres' => $data['size_acres']]);
        [$errors, $warnings, $clean] = PlotImporter::validate($draft, $parsed['rows']);
        if ($errors) {
            return back()->withInput()->with('plotErrors', $errors)->with('error', 'Fix the plot rows listed below. Nothing was saved — choose the photos and layout again when you resubmit.');
        }

        $project = DB::transaction(function () use ($request, $data, $tenant, $clean, $contact) {
            $project = Project::create([
                'tenant_id' => $tenant->id,
                'project_code' => IdGenerator::next($tenant->id, 'project'),
                'name' => $data['name'], 'location' => $data['location'], 'village' => $data['village'] ?? null, 'district' => $data['district'],
                'state' => $data['state'], 'pin' => $data['pin'], 'address' => $data['address'] ?? null, 'lat' => $data['lat'] ?? null, 'lng' => $data['lng'] ?? null,
                'approval_type' => $data['approval_type'], 'approval_authority' => $data['approval_authority'] ?? null, 'approval_number' => $data['approval_number'] ?? null,
                'survey_numbers' => $data['survey_numbers'], 'patta_numbers' => $data['patta_numbers'], 'size_acres' => $data['size_acres'],
                'promo_text' => $data['promo_text'], 'youtube_url' => $data['youtube_url'] ?? null,
                'map_url' => isset($data['lat'], $data['lng']) ? 'https://www.openstreetmap.org/?mlat='.$data['lat'].'&mlon='.$data['lng'].'#map=17/'.$data['lat'].'/'.$data['lng'] : null,
                'email' => $contact['contact_email'] ?? null, 'contact' => $contact['contact_mobile'],
                'status' => 'ready_to_launch', 'launch_mode' => 'direct', 'progress_pct' => 100,
                'created_by' => $request->user()->id,
            ]);
            $layout = FileStore::store($request->file('layout'), 'layout', $tenant->id, $project, 'layout');
            $dims = str_starts_with($layout->mime, 'image/') ? @getimagesize($request->file('layout')->getRealPath()) : null;
            $project->update(['layout_file_id' => $layout->id, 'layout_width' => $dims[0] ?? null, 'layout_height' => $dims[1] ?? null]);
            PropertyMedia::addImages($project, $request->file('images'), (array) $request->input('captions', []));
            if ($request->hasFile('brochure')) {
                PropertyMedia::brochure($project, $request->file('brochure'));
            }
            PropertyDetails::syncFeatures($project, (array) ($data['features'] ?? []), (array) ($data['highlights'] ?? []), 'layout');
            PropertyDetails::saveContact($project, $contact);
            PlotImporter::import($project, $clean, $request->user()->id);
            $project->update(['launched_at' => now()]);
            ProjectService::setStatus($project->fresh(), 'launched', 'Published with Quick Launch (direct launch).');

            return $project->fresh();
        });
        SeoService::refreshProject($project);
        MatchRequirements::dispatch($project->id);

        return redirect()->route('ws.launch.show', $project)->with('ok', "{$project->name} is live on the marketplace with ".$project->plots()->count().' plots. Place the plot pins on the layout so buyers can tap them.');
    }

    /** Convert a direct-launch layout into a full project so stage tracking can start (plots and bookings stay). */
    public function convert(Request $request, Project $project)
    {
        abort_unless($project->isDirect(), 404);
        ProjectService::startTracking($project, $request->user()->id);

        return redirect()->route('ws.projects.show', $project)->with('ok', 'Converted to a full project. Stages and sub-tasks are ready for tracking; plots and bookings are unchanged.');
    }
}
