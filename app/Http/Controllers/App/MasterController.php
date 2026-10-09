<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\FileStore;
use App\Services\MasterService;
use App\Services\TemplateExporter;
use App\Services\TemplateImporter;
use Illuminate\Http\Request;

/** App-level prerequisite masters (templates shared read-only with all tenants) + template import/export. */
class MasterController extends Controller
{
    public function index(Request $request, string $type = 'stages')
    {
        $u = $request->user();

        return view('masters.index', MasterService::data($type, null, $request) + [
            'type' => $type, 'scope' => 'app', 'canEdit' => $u->hasPerm('prerequisites.update'), 'canCreate' => $u->hasPerm('prerequisites.create'), 'canDelete' => $u->hasPerm('prerequisites.delete'),
        ]);
    }

    public function store(Request $request, string $type)
    {
        abort_unless(isset(MasterService::TYPES[$type]), 404);
        MasterService::save($type, null, $request);

        return back()->with('ok', 'Saved.');
    }

    public function update(Request $request, string $type, int $id)
    {
        abort_unless(isset(MasterService::TYPES[$type]), 404);
        MasterService::save($type, null, $request, $id);

        return back()->with('ok', 'Saved.');
    }

    public function destroy(Request $request, string $type, int $id)
    {
        abort_unless(isset(MasterService::TYPES[$type]), 404);
        MasterService::delete($type, null, $request, $id);

        return back()->with('ok', 'Deleted.');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => FileStore::rules('import', 10240)]);
        $imp = TemplateImporter::fromFile($request->file('file')->getRealPath());
        if (! $imp->validate(false)) {
            return back()->with('import_errors', $imp->errors)->with('error', 'Nothing was imported. Fix the errors below and upload again.');
        }
        $summary = $imp->import(null);

        return back()->with('ok', 'App masters replaced: '.$summary['stages'].' stages, '.$summary['tasks'].' sub-tasks, '.$summary['facilities'].' facilities, '.$summary['documents'].' documents.');
    }

    public function export()
    {
        $path = tempnam(sys_get_temp_dir(), 'v7tpl').'.xlsx';
        TemplateExporter::write(null, $path);

        return response()->download($path, 'Vector7_App_Masters_'.now()->format('Ymd').'.xlsx')->deleteFileAfterSend();
    }
}
