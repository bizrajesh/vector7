<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\MasterService;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/** Tenant's own copies of the masters (copied from the App templates, then editable). SRO list is read-only here. */
class MasterController extends Controller
{
    public function index(Request $request, string $type = 'stages')
    {
        return view('masters.index', MasterService::data($type, app(Tenancy::class)->id(), $request) + [
            'type' => $type, 'scope' => 'ws', 'canEdit' => $request->user()->hasPerm('masters.update') && $type !== 'sros',
            'canCreate' => $request->user()->hasPerm('masters.create') && $type !== 'sros', 'canDelete' => $request->user()->hasPerm('masters.delete') && $type !== 'sros',
        ]);
    }

    public function store(Request $request, string $type)
    {
        abort_if($type === 'sros' || ! isset(MasterService::TYPES[$type]), 404);
        MasterService::save($type, app(Tenancy::class)->id(), $request);

        return back()->with('ok', 'Saved.');
    }

    public function update(Request $request, string $type, int $id)
    {
        abort_if($type === 'sros' || ! isset(MasterService::TYPES[$type]), 404);
        MasterService::save($type, app(Tenancy::class)->id(), $request, $id);

        return back()->with('ok', 'Saved.');
    }

    public function destroy(Request $request, string $type, int $id)
    {
        abort_if($type === 'sros' || ! isset(MasterService::TYPES[$type]), 404);
        MasterService::delete($type, app(Tenancy::class)->id(), $request, $id);

        return back()->with('ok', 'Deleted.');
    }
}
