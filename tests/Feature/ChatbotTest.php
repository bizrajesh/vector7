<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\Lead;
use App\Services\AppSettings;
use App\Services\MarketBot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Marketplace chatbot (17.9) and workspace chatbot (17.10). */
class ChatbotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
    }

    private function enableAi(): void
    {
        AppSettings::set('ai.enabled', '1');
        AppSettings::set('ai.api_key', 'sk-test');
    }

    private static function api(array $content): array
    {
        return ['content' => $content, 'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 5, 'output_tokens' => 5]];
    }

    public function test_market_bot_answers_from_database_without_ai(): void
    {
        [$tenant] = $this->makeTenant('Bot Co');
        $l = ListingsMarketplaceTest::makeListing($tenant);
        AppSettings::set('ai.enabled', '0');
        Http::fake();
        $this->getJson(route('market.chat'))->assertOk()->assertJsonPath('name', 'Vee');
        $r = $this->postJson(route('market.chat.send'), ['message' => '2 bhk flat in Thanjavur'])->assertOk();
        $this->assertSame('L:'.$l->id, $r->json('cards.0.key'));
        $this->assertStringNotContainsString('9840012345', $r->getContent());
        Http::assertNothingSent();
        $c = ChatConversation::where('scope', 'market')->firstOrFail();
        $this->assertSame(2, $c->messages()->count());
        // FAQ answer
        $this->postJson(route('market.chat.send'), ['message' => 'Is it free for buyers?'])->assertOk()->assertJsonPath('text', 'Yes. Browsing, enquiring and booking on vector7 is free for buyers.');
        // Sold listings never come back.
        $l->update(['status' => 'sold']);
        $this->postJson(route('market.chat.send'), ['message' => '2 bhk flat in Thanjavur'])->assertOk()->assertJsonCount(0, 'cards');
    }

    public function test_market_bot_uses_tools_and_create_lead_requires_visitor_consent(): void
    {
        [$tenant] = $this->makeTenant('Bot AI Co');
        $l = ListingsMarketplaceTest::makeListing($tenant);
        $this->enableAi();
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(self::api([['type' => 'tool_use', 'id' => 't1', 'name' => 'search_properties', 'input' => ['types' => ['flat'], 'places' => ['Thanjavur']]]]))
            ->push(self::api([['type' => 'text', 'text' => 'I found the Sunny 2 BHK Flat for you.']]))
            ->push(self::api([['type' => 'tool_use', 'id' => 't2', 'name' => 'create_lead', 'input' => ['key' => 'L:'.$l->id, 'note' => 'Site visit Sunday']]]))
            ->push(self::api([['type' => 'text', 'text' => 'Please confirm below and the owner will call you.']])),
        ]);
        $r = $this->postJson(route('market.chat.send'), ['message' => 'flats in thanjavur'])->assertOk();
        $this->assertSame('I found the Sunny 2 BHK Flat for you.', $r->json('text'));
        $this->assertSame('L:'.$l->id, $r->json('cards.0.key'));
        // The tool result sent to Claude never contains the owner's number.
        Http::assertSent(fn ($req) => ! str_contains($req->body(), '9840012345'));

        $r = $this->postJson(route('market.chat.send'), ['message' => 'Please ask the owner to call me'])->assertOk();
        $this->assertSame('create_lead', $r->json('action.type'));
        $this->assertSame(0, Lead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(), 'The bot alone must not create a lead.');

        $this->postJson(route('market.chat.consent'), ['key' => 'L:'.$l->id, 'name' => 'Asha', 'mobile' => '9876501234'])->assertStatus(422)->assertJsonValidationErrors('consent');
        $this->postJson(route('market.chat.consent'), ['key' => 'L:999', 'name' => 'Asha', 'mobile' => '9876501234', 'consent' => 1])->assertStatus(422);
        $this->postJson(route('market.chat.consent'), ['key' => 'L:'.$l->id, 'name' => 'Asha', 'mobile' => '9876501234', 'consent' => 1, 'note' => 'Site visit Sunday'])->assertOk();
        $lead = Lead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('chatbot', $lead->source);
        $this->assertTrue((bool) $lead->consent);
    }

    public function test_market_bot_falls_back_when_ai_errors_and_purges_old_chats(): void
    {
        [$tenant] = $this->makeTenant('Bot Err Co');
        ListingsMarketplaceTest::makeListing($tenant);
        $this->enableAi();
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'overloaded']], 529)]);
        $this->postJson(route('market.chat.send'), ['message' => 'flat in Thanjavur'])->assertOk()->assertJsonCount(1, 'cards');
        $old = ChatConversation::create(['scope' => 'market', 'visitor_token' => 'old', 'last_message_at' => now()->subDays(91)]);
        $this->assertSame(1, MarketBot::purge());
        $this->assertNull(ChatConversation::find($old->id));
        $this->assertSame(1, ChatConversation::count());
    }
}
