<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ReportService;
use App\Support\Excel;
use App\Support\Pdf;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/** Ten standard reports — each with filters, an on-screen table and Excel / PDF export. */
class ReportController extends Controller
{
    public function index()
    {
        return view('ws.reports.index', ['reports' => ReportService::REPORTS]);
    }

    public function show(Request $request, string $report)
    {
        abort_unless(isset(ReportService::REPORTS[$report]), 404);
        $f = $request->validate([
            'project' => 'nullable|integer',
            'status' => 'nullable|string|max:30',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'overdue' => 'nullable|boolean',
            'export' => 'nullable|in:xlsx,pdf',
        ]);
        [$title, $desc, $filters] = ReportService::REPORTS[$report];
        $data = ReportService::run($report, $f);
        $sub = collect([
            ! empty($f['project']) ? Project::find($f['project'])?->name : null,
            ! empty($f['status']) ? (ReportService::statusOptions($report)[$f['status']] ?? $f['status']) : null,
            ! empty($f['from']) || ! empty($f['to']) ? trim(($f['from'] ?? '…').' to '.($f['to'] ?? 'today')) : null,
            ! empty($f['overdue']) ? 'Overdue only' : null,
        ])->filter()->join(' · ');

        $export = $f['export'] ?? null;
        if ($export) {
            abort_unless($request->user()->hasPerm('reports.export'), 403);
            \App\Services\AuditLogger::log('report_export', null, null, ['report' => $title, 'format' => $export, 'filters' => $sub]);
        }
        if ($export === 'xlsx') {
            $rows = $data['rows'];
            if ($data['totals']) {
                $rows[] = $data['totals'];
            }

            return Excel::download("$report.xlsx", $data['headers'], $rows, $title.($sub ? " — $sub" : ''));
        }
        if ($export === 'pdf') {
            return Pdf::download('pdf.report', ['report' => $data, 'docTitle' => $title, 'docSub' => $sub ?: 'All records', 'tenant' => app(Tenancy::class)->get()],
                "$report-".now()->format('dmY').'.pdf', count($data['headers']) > 7 ? 'landscape' : 'portrait');
        }

        return view('ws.reports.show', [
            'key' => $report, 'title' => $title, 'desc' => $desc, 'filters' => $filters, 'report' => $data, 'sub' => $sub,
            'projects' => Project::orderBy('name')->pluck('name', 'id'), 'statuses' => ReportService::statusOptions($report),
        ]);
    }
}
