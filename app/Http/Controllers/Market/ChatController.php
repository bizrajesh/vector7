<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Services\AppSettings;
use App\Services\MarketBot;
use Illuminate\Http\Request;

/** Marketplace chatbot endpoints (JSON). The visitor is identified by a token kept in the session. */
class ChatController extends Controller
{
    public function history(Request $request)
    {
        $c = MarketBot::conversation($request->session()->get('chat_token'), auth('customer')->id(), false);

        return response()->json([
            'name' => AppSettings::get('ai.bot_name', 'Vee'),
            'greeting' => AppSettings::get('ai.bot_greeting'),
            'messages' => $c ? ChatMessage::where('chat_conversation_id', $c->id)->latest('id')->take(30)->get()->reverse()->values()
                ->map(fn ($m) => ['role' => $m->role, 'text' => $m->content, 'cards' => $m->meta['cards'] ?? [], 'action' => null]) : [],
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate(['message' => 'required|string|max:1000']);
        $c = MarketBot::conversation($request->session()->get('chat_token'), auth('customer')->id());
        $request->session()->put('chat_token', $c->visitor_token);
        $out = MarketBot::reply($c, $data['message']);

        return response()->json(['text' => $out['text'], 'cards' => $out['cards'], 'action' => $out['action']]);
    }

    /** The visitor ticks consent to share name + mobile with the owner (create_lead needs this — the bot cannot do it alone). */
    public function consent(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'regex:/^[LP]:\d+$/'],
            'name' => 'required|string|max:100',
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'note' => 'nullable|string|max:300',
            'consent' => 'accepted',
        ], ['mobile.regex' => 'Enter a 10-digit mobile number.', 'consent.accepted' => 'Please tick the box to agree to share your details.']);
        $c = MarketBot::conversation($request->session()->get('chat_token'), auth('customer')->id(), false) ?? abort(404);
        $pending = ChatMessage::where('chat_conversation_id', $c->id)->where('role', 'assistant')->latest('id')->take(10)->get()
            ->contains(fn ($m) => ($m->meta['action']['key'] ?? null) === $data['key']);
        abort_unless($pending, 422, 'Ask the assistant to contact the owner first.');
        MarketBot::confirmLead($c, $data['key'], $data['name'], $data['mobile'], $data['note'] ?? null, auth('customer')->id());
        $request->session()->put('contact_person', ['name' => $data['name'], 'mobile' => $data['mobile']]);

        return response()->json(['text' => 'Thanks '.$data['name'].'! The owner will contact you soon.']);
    }
}
