<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Lead;
use App\Services\LeadService;
use App\Services\WorkspaceBot;
use App\Support\Excel;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Workspace assistant endpoints (spec 17.10). */
class AssistantController extends Controller
{
    public function history(Request $request)
    {
        abort_unless(WorkspaceBot::enabledFor($request->user()), 403);
        $c = ChatConversation::where('scope', 'workspace')->where('user_id', $request->user()->id)->where('last_message_at', '>', now()->subHours(12))->latest('id')->first();

        return response()->json([
            'greeting' => 'Hi '.strtok($request->user()->name, ' ').'! Ask how to do something in vector7, or about your leads, plots, dues and expenses. I can also draft messages and descriptions.',
            'messages' => $c ? ChatMessage::where('chat_conversation_id', $c->id)->latest('id')->take(30)->get()->reverse()->values()->map(fn ($m) => [
                'role' => $m->role, 'text' => $m->content, 'table' => isset($m->meta['table']) ? $m->meta['table'] + ['export' => route('ws.assistant.export', $m)] : null,
            ]) : [],
        ]);
    }

    public function send(Request $request)
    {
        abort_unless(WorkspaceBot::enabledFor($request->user()), 403, 'The assistant is switched off for this workspace.');
        $out = WorkspaceBot::reply($request->user(), $request->validate(['message' => 'required|string|max:2000'])['message']);

        return response()->json(['text' => $out['text'], 'table' => $out['table'], 'action' => $out['action']]);
    }

    /** "Create follow-up" — runs only after the user pressed Confirm; permission and visibility re-checked here. */
    public function followup(Request $request)
    {
        $user = $request->user();
        abort_unless(WorkspaceBot::enabledFor($user) && WorkspaceBot::allowed($user, 'propose_followup'), 403);
        $data = $request->validate(['lead_code' => 'required|string|max:40', 'due_at' => 'required|date|after:now', 'kind' => 'required|in:follow_up,site_visit', 'notes' => 'nullable|string|max:500']);
        $lead = Lead::visibleTo($user)->where('lead_code', $data['lead_code'])->firstOrFail();
        LeadService::scheduleFollowup($lead, Carbon::parse($data['due_at']), $data['kind'], $data['notes'] ?? null, $lead->assigned_to ?? $user->id);

        return response()->json(['text' => ($data['kind'] === 'site_visit' ? 'Site visit' : 'Follow-up').' created for '.$lead->name.' on '.Carbon::parse($data['due_at'])->format('d-m-Y h:i A').'.']);
    }

    /** Excel download of a table the assistant showed (only the owner of the conversation). */
    public function export(Request $request, ChatMessage $message)
    {
        $c = $message->conversation;
        abort_unless($c && $c->scope === 'workspace' && $c->user_id === $request->user()->id && isset($message->meta['table']), 404);
        $t = $message->meta['table'];

        return Excel::download(\Illuminate\Support\Str::slug($t['title'] ?? 'assistant').'.xlsx', $t['columns'], $t['rows'], mb_substr($t['title'] ?? 'Assistant', 0, 31));
    }
}
