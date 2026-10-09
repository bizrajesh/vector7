<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\StageMaster;
use App\Services\FileStore;
use App\Services\TemplateExporter;
use App\Services\TemplateImporter;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/**
 * Setup wizard after sign-up: organisation profile → download/upload pre-configuration template
 * (or "use App defaults") → validate → import → invite users.
 */
class SetupController extends Controller
{
    public function show()
    {
        $tenant = app(Tenancy::class)->get();

        return view('ws.setup', ['tenant' => $tenant, 'step' => $tenant->onboarding_step, 'hasMasters' => StageMaster::exists()]);
    }

    public function profile(Request $request)
    {
        $tenant = app(Tenancy::class)->get();
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'address_line1' => 'required|string|max:150',
            'address_line2' => 'nullable|string|max:150',
            'village' => 'nullable|string|max:100',
            'city' => 'required|string|max:100',
            'district' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pin' => ['required', 'regex:/^\d{6}$/'],
            'contact' => ['required', 'regex:/^\d{10}$/'],
            'support_email' => 'nullable|email',
        ], ['pin.regex' => 'PIN must be 6 digits.', 'contact.regex' => 'Contact must be a 10-digit mobile number.']);
        $tenant->update($data + ['onboarding_step' => max(2, $tenant->onboarding_step)]);

        return redirect()->route('ws.setup')->with('ok', 'Profile saved. Next: load your masters.');
    }

    /** Upload the filled template: validate everything first, import nothing on any error. */
    public function template(Request $request)
    {
        $request->validate(['file' => FileStore::rules('import', 10240)]);
        $tenant = app(Tenancy::class)->get();
        $imp = TemplateImporter::fromFile($request->file('file')->getRealPath());
        if (! $imp->validate(true, $tenant->id)) {
            return back()->with('import_errors', $imp->errors)->with('error', 'Nothing was imported. Fix the '.count($imp->errors).' error(s) listed below and upload the file again.');
        }
        $summary = $imp->import($tenant);
        $tenant->update(['onboarding_step' => max(4, $tenant->onboarding_step)]);
        $msg = "Imported {$summary['stages']} stages, {$summary['tasks']} sub-tasks, {$summary['facilities']} facilities, {$summary['documents']} documents"
            .(isset($summary['users_created']) ? ", {$summary['users_created']} users" : '').'.';

        return back()->with('ok', $msg)->with('warnings', $imp->warnings);
    }

    public function defaults()
    {
        $tenant = app(Tenancy::class)->get();
        TenantProvisioner::copyAppMasters($tenant);
        $tenant->update(['onboarding_step' => max(4, $tenant->onboarding_step)]);

        return redirect()->route('ws.setup')->with('ok', 'App default masters copied into your workspace. You can edit them any time in Masters.');
    }

    public function finish()
    {
        $tenant = app(Tenancy::class)->get();
        if (! StageMaster::exists()) {
            TenantProvisioner::copyAppMasters($tenant);
        }
        $tenant->update(['onboarding_step' => 5]);

        return redirect()->route('ws.dashboard')->with('ok', 'Setup complete. Create your first project!');
    }

    /** The blank template (with App defaults in the master sheets). */
    public function download()
    {
        return response()->download(database_path('seeders/data/Vector7_Tenant_PreConfig_Template.xlsx'), 'Vector7_Tenant_PreConfig_Template.xlsx');
    }

    /** Export the tenant's current setup in the same template format. */
    public function export()
    {
        $tenant = app(Tenancy::class)->get();
        $path = tempnam(sys_get_temp_dir(), 'v7tpl').'.xlsx';
        TemplateExporter::write($tenant, $path);

        return response()->download($path, 'Vector7_PreConfig_'.$tenant->code.'.xlsx')->deleteFileAfterSend();
    }
}
