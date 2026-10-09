<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectSubtask;
use App\Services\FileStore;
use App\Services\ProjectService;
use Illuminate\Http\Request;

/** Approval tracking: sub-task status, actual dates & cost, notes, checklist documents; progress roll-up. */
class TrackingController extends Controller
{
    public function show(Request $request, Project $project)
    {
        $project->load(['stages.subtasks.documents.file']);
        $filter = $request->query('filter');

        return view('ws.projects.tracking', [
            'project' => $project,
            'filter' => $filter,
            'gaps' => $project->status === 'in_progress' ? ProjectService::readinessGaps($project) : [],
            'delayed' => $project->subtasks()->where('status', '!=', 'done')->whereDate('planned_end', '<', today())->count(),
            'canEdit' => $request->user()->hasPerm('tracking.update') && $project->status === 'in_progress',
        ]);
    }

    public function update(Request $request, Project $project, ProjectSubtask $subtask)
    {
        abort_unless($subtask->project_id === $project->id, 404);
        $data = $request->validate([
            'status' => 'required|in:not_started,in_progress,blocked,done',
            'actual_start' => 'nullable|date',
            'actual_end' => 'nullable|date|after_or_equal:actual_start',
            'actual_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);
        $data['actual_cost'] = $data['actual_cost'] ?? 0;
        try {
            ProjectService::updateSubtask($subtask, array_filter($data, fn ($v) => $v !== null && $v !== ''), $request->user()->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', "{$subtask->task_code}: ".collect($e->errors())->flatten()->first());
        }

        return back()->with('ok', "{$subtask->task_code} updated. Progress recalculated.");
    }

    public function upload(Request $request, Project $project, ProjectDocument $document)
    {
        abort_unless($document->project_id === $project->id, 404);
        abort_if($project->isReadOnly(), 403);
        $request->validate(['file' => FileStore::rules('document', 20480)]);
        $old = $document->file;
        $file = FileStore::store($request->file('file'), 'project-documents', $project->tenant_id, $document);
        $document->update(['storage_file_id' => $file->id, 'uploaded_at' => now(), 'uploaded_by' => $request->user()->id]);
        if ($old) {
            FileStore::delete($old);
        }

        return back()->with('ok', "{$document->doc_code} uploaded.");
    }

    public function ready(Project $project)
    {
        try {
            ProjectService::markReady($project);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('ws.launch.index')->with('ok', "{$project->name} is Ready to Launch.");
    }
}
