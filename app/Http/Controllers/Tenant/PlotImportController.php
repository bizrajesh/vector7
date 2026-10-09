<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\FileStore;
use App\Services\PlotImporter;
use App\Support\Excel;
use Illuminate\Http\Request;

/** CSV / Excel / AI-extracted plots → editable preview grid → validate → import (nothing until every error is fixed). */
class PlotImportController extends Controller
{
    public function preview(Request $request, Project $project)
    {
        $this->guard($project);
        $request->validate([
            'csv' => 'nullable|string|max:500000',
            'file' => FileStore::rules('import', 10240, false),
        ]);
        if ($request->hasFile('file')) {
            $f = $request->file('file');
            $parsed = PlotImporter::readUpload($f->getRealPath(), $f->getClientOriginalExtension());
        } elseif (trim((string) $request->input('csv')) !== '') {
            $parsed = PlotImporter::fromTable(Excel::parseCsvText($request->input('csv')));
        } else {
            return back()->with('error', 'Paste CSV text or choose a CSV/Excel file.');
        }
        if ($parsed['header_error']) {
            return back()->with('error', $parsed['header_error']);
        }

        return $this->render($project, $parsed['rows'], 'file');
    }

    public function extract(Request $request, Project $project)
    {
        $this->guard($project);
        $request->validate(['source' => FileStore::rules('extract', 20480)]);
        $file = FileStore::store($request->file('source'), 'layout-source', $project->tenant_id, $project, 'extract');
        try {
            $rows = PlotImporter::extract($file, $project);
        } catch (\App\Exceptions\PlanLimitException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        if (! $rows) {
            return back()->with('error', 'No plots were found in this file. For DXF, plots must be closed polylines; for PDF/photos make sure the plot numbers and sizes are readable.');
        }
        foreach ($rows as $i => &$r) {
            $r['_row'] = $i + 1;
        }

        return $this->render($project, $rows, 'ai', 'Plots extracted from '.$file->original_name.'. Check every value, fill patta numbers, facing and rates, then import.');
    }

    public function confirm(Request $request, Project $project)
    {
        $this->guard($project);
        $request->validate(['rows_json' => 'required|string|max:5000000']);
        $rows = json_decode($request->input('rows_json'), true);
        if (! is_array($rows) || count($rows) > 3000) {
            return back()->with('error', 'The grid data could not be read.');
        }
        foreach ($rows as $i => &$r) {
            $r = is_array($r) ? $r : [];
            $r['_row'] = $i + 1;
            if (isset($r['map_polygon']) && is_string($r['map_polygon'])) {
                $r['map_polygon'] = json_decode($r['map_polygon'], true) ?: null;
            }
        }
        [$errors, , $clean] = PlotImporter::validate($project, $rows);
        if ($errors) {
            return $this->render($project, $rows, 'grid');
        }
        $summary = PlotImporter::import($project, $clean, $request->user()->id);

        return redirect()->route('ws.launch.show', $project)->with('ok', "Plots imported: {$summary['created']} new, {$summary['updated']} updated.");
    }

    private function render(Project $project, array $rows, string $source, ?string $note = null)
    {
        foreach ($rows as $i => &$r) {
            $r['_row'] = $i + 1;
        }
        [$rowErrors, $warnings] = PlotImporter::validate($project, $rows);

        return response()->view('ws.launch.preview', compact('project', 'rows', 'rowErrors', 'warnings', 'source', 'note'));
    }

    private function guard(Project $project): void
    {
        abort_unless(in_array($project->status, ['ready_to_launch', 'launched'], true), 404);
    }
}
