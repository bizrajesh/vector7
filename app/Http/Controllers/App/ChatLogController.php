<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Tenant;
use App\Support\Excel;
use Illuminate\Http\Request;

/** Chatbot conversations (spec 17.9 / 17.10): kept 90 days; App Admin can view and export them. */
class ChatLogController extends Controller
{
    private function query(Request $request)
    {
        return ChatConversation::with(['customer:id,name,email', 'user:id,name,email', 'tenant:id,name'])
            ->when($request->query('scope'), fn ($q, $s) => $q->where('scope', $s))
            ->when($request->query('tenant'), fn ($q, $t) => $q->where('tenant_id', $t))
            ->when($request->query('q'), fn ($q, $s) => $q->whereHas('messages', fn ($m) => $m->where('content', 'like', "%$s%")))
            ->when($request->date('from'), fn ($q, $d) => $q->whereDate('last_message_at', '>=', $d))
            ->when($request->date('to'), fn ($q, $d) => $q->whereDate('last_message_at', '<=', $d))
            ->latest('last_message_at');
    }

    public function index(Request $request)
    {
        if ($request->query('export') === 'xlsx') {
            $ids = $this->query($request)->limit(2000)->pluck('id');
            $rows = ChatMessage::whereIn('chat_conversation_id', $ids)->with('conversation')->orderBy('chat_conversation_id')->orderBy('id')->cursor()
                ->map(fn ($m) => [$m->chat_conversation_id, $m->conversation->scope, $m->created_at, $m->role, $m->content]);

            return Excel::download('chatbot-conversations.xlsx', ['Conversation', 'Bot', 'Time', 'From', 'Message'], $rows, 'Chatbot conversations');
        }

        return view('app.chats.index', [
            'conversations' => $this->query($request)->paginate(30)->withQueryString(),
            'tenants' => Tenant::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function show(ChatConversation $conversation)
    {
        return view('app.chats.show', ['c' => $conversation->load(['messages', 'customer', 'user', 'tenant'])]);
    }
}
