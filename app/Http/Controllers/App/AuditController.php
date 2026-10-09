<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Support\Excel;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $q = AuditLog::latest('id')
            ->when($request->query('tenant'), fn ($q, $t) => $q->where('tenant_id', $t))
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', $a))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('actor_name', 'like', "%$s%")->orWhere('auditable_type', 'like', "%$s%")->orWhere('after', 'like', "%$s%")))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
        if ($request->query('export') === 'xlsx') {
            return Excel::download('audit-log.xlsx', ['When', 'Tenant', 'Who', 'Action', 'Record', 'ID', 'Before', 'After', 'IP'],
                $q->take(20000)->get()->map(fn ($l) => [$l->created_at->format('d-m-Y H:i:s'), $l->tenant_id, $l->actor_name, $l->action, $l->auditable_type, $l->auditable_id, json_encode($l->before), json_encode($l->after), $l->ip]), 'Audit log');
        }

        return view('app.audit', [
            'logs' => $q->paginate(50)->withQueryString(),
            'tenants' => Tenant::orderBy('name')->pluck('name', 'id'),
            'actions' => AuditLog::distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
