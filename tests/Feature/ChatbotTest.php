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
        // Other tools read the database / settings only.
        [$faq] = MarketBot::runTool('get_faq', ['topic' => 'who do I pay']);
        $this->assertStringContainsString('directly to the promoter', $faq['answer']);
        [$info] = MarketBot::runTool('get_promoter_public_info', ['key' => 'L:'.$l->id]);
        $this->assertSame($tenant->name, $info['name']);
        $this->assertArrayNotHasKey('mobile', $info);
        $this->assertArrayHasKey('services', MarketBot::runTool('get_services', [])[0]);
        $p = LaunchTest::readyProject($tenant);
        $p->update(['status' => 'launched', 'launched_at' => now()]);
        app(\App\Support\Tenancy::class)->run($tenant, fn () => \App\Models\Plot::create(['tenant_id' => $tenant->id, 'project_id' => $p->id, 'plot_no' => '9', 'patta_number' => '9', 'size_sqft' => 1200, 'facing' => 'East', 'rate_per_sqft' => 1000]));
        $this->assertSame('9', MarketBot::runTool('get_layout_plots', ['key' => 'P:'.$p->id, 'facing' => 'East'])[0]['plots'][0]['plot_no']);
        $this->assertSame([], MarketBot::runTool('get_layout_plots', ['key' => 'P:'.$p->id, 'facing' => 'West'])[0]['plots']);
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
            ->push(self::api([['type' => 'tool_use', 'id' => 't1', 'name' => 'search_listings', 'input' => ['types' => ['flat'], 'places' => ['Thanjavur']]]]))
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

    // ------------------------------------------------------------------ 17.10 workspace assistant

    public function test_workspace_bot_tools_respect_role_and_tenant(): void
    {
        [$tenant, $admin] = $this->makeTenant('WB Co');
        $sales = $this->makeUser($tenant, 'tenant_sales');
        $accounts = $this->makeUser($tenant, 'tenant_account');
        $mine = \App\Services\LeadService::capture($tenant->id, 'manual', ['name' => 'Own Lead', 'mobile' => '9000000011']);
        $other = \App\Services\LeadService::capture($tenant->id, 'manual', ['name' => 'Colleague Lead', 'mobile' => '9000000012']);
        $s2 = $this->makeUser($tenant, 'tenant_sales');
        app(\App\Support\Tenancy::class)->run($tenant, function () use ($mine, $other, $sales, $s2) {
            \App\Services\LeadService::assign($mine, $sales);
            \App\Services\LeadService::assign($other, $s2);
        });
        [$t2, $admin2] = $this->makeTenant('WB Other');
        $foreign = \App\Services\LeadService::capture($t2->id, 'manual', ['name' => 'Foreign Lead', 'mobile' => '9000000013']);

        // Sales: own + unassigned leads only; Support: no money data.
        [$res, $table] = \App\Services\WorkspaceBot::runTool($sales, 'list_leads', []);
        $this->assertSame(['Own Lead'], array_column($res['leads'], 'name'));
        $this->assertSame('Leads', $table['title']);
        // A Sales user can't get accounts totals (spec 17.13).
        $this->assertArrayHasKey('error', \App\Services\WorkspaceBot::runTool($sales, 'expense_summary', [])[0]);
        $this->assertArrayHasKey('error', \App\Services\WorkspaceBot::runTool($sales, 'project_summary', ['project' => 'x'])[0]);
        $support = $this->makeUser($tenant, 'support');
        $this->assertArrayHasKey('error', \App\Services\WorkspaceBot::runTool($support, 'expense_summary', [])[0]);
        $this->assertArrayHasKey('error', \App\Services\WorkspaceBot::runTool($support, 'list_due_instalments', ['period' => 'overdue'])[0]);
        $this->assertArrayHasKey('error', \App\Services\WorkspaceBot::runTool($sales, 'lead_details', ['lead_code' => $other->lead_code])[0]);
        // Accounts: expenses yes, leads no.
        $this->assertArrayHasKey('total', \App\Services\WorkspaceBot::runTool($accounts, 'expense_summary', [])[0]);
        $this->assertArrayHasKey('error', \App\Services\WorkspaceBot::runTool($accounts, 'list_leads', [])[0]);
        // Admin sees all of their own tenant, never another tenant's.
        $names = array_column(\App\Services\WorkspaceBot::runTool($admin, 'list_leads', [])[0]['leads'], 'name');
        $this->assertEqualsCanonicalizing(['Own Lead', 'Colleague Lead'], $names);
        // (lead codes are per workspace, so the same code may exist in both — it must resolve to this workspace's lead or nothing)
        $this->assertNotSame('Foreign Lead', \App\Services\WorkspaceBot::runTool($admin, 'lead_details', ['lead_code' => $foreign->lead_code])[0]['name'] ?? null);
        $this->assertSame('Foreign Lead', \App\Services\WorkspaceBot::runTool($admin2, 'lead_details', ['lead_code' => $foreign->lead_code])[0]['name']);
        // The tool list sent to Claude only has allowed tools.
        $this->enableAi();
        Http::fake(['api.anthropic.com/*' => Http::response(self::api([['type' => 'text', 'text' => 'Hello']]))]);
        $this->actingAs($accounts)->postJson(route('ws.assistant.send'), ['message' => 'hi'])->assertOk();
        Http::assertSent(fn ($r) => str_contains($r->body(), 'expense_summary') && ! str_contains($r->body(), 'list_leads'));
        // Workspace users never get App tools, App users never get workspace data tools.
        $this->assertArrayHasKey('error', \App\Services\WorkspaceBot::runTool($admin, 'app_tenants', [])[0]);
        $app = $this->appAdmin();
        $this->assertArrayHasKey('error', \App\Services\WorkspaceBot::runTool($app, 'list_leads', [])[0]);
        [$res, $table] = \App\Services\WorkspaceBot::runTool($app, 'app_tenants', ['search' => 'WB']);
        $this->assertEqualsCanonicalizing(['WB Co', 'WB Other'], array_column($res['tenants'], 'name'));
        $this->assertCount(2, $table['links']);
        $this->actingAs($app)->getJson(route('app.assistant'))->assertOk();
        // Links in tables open the record.
        $this->assertStringContainsString('/workspace/leads/', \App\Services\WorkspaceBot::runTool($admin, 'list_leads', [])[1]['links'][0]);
        $this->assertSame('Plan usage — Professional', \App\Services\WorkspaceBot::runTool($admin, 'usage_summary', [])[1]['title']);
    }

    public function test_workspace_bot_followup_needs_confirm_and_export(): void
    {
        [$tenant, $admin] = $this->makeTenant('WB Confirm');
        $lead = \App\Services\LeadService::capture($tenant->id, 'manual', ['name' => 'Visit Lead', 'mobile' => '9000000021']);
        $this->enableAi();
        $due = now()->addDay()->setTime(11, 0)->format('Y-m-d H:i');
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(self::api([['type' => 'tool_use', 'id' => 'a', 'name' => 'propose_followup', 'input' => ['lead_code' => $lead->lead_code, 'due_at' => $due, 'kind' => 'site_visit', 'notes' => 'Bring EC copy']]]))
            ->push(self::api([['type' => 'text', 'text' => 'Please confirm the site visit.']]))
            ->push(self::api([['type' => 'tool_use', 'id' => 'b', 'name' => 'list_leads', 'input' => []]]))
            ->push(self::api([['type' => 'text', 'text' => 'You have 1 lead.']])),
        ]);
        $r = $this->actingAs($admin)->postJson(route('ws.assistant.send'), ['message' => 'Schedule a site visit for '.$lead->lead_code.' tomorrow 11am'])->assertOk();
        $this->assertSame('confirm', $r->json('action.type'));
        $this->assertSame(0, \App\Models\LeadFollowup::withoutGlobalScopes()->count(), 'Nothing is written before Confirm.');
        $this->actingAs($admin)->postJson($r->json('action.url'), $r->json('action.payload'))->assertOk();
        $this->assertSame(1, \App\Models\LeadFollowup::withoutGlobalScopes()->where('lead_id', $lead->id)->where('kind', 'site_visit')->count());

        $r = $this->actingAs($admin)->postJson(route('ws.assistant.send'), ['message' => 'list my leads'])->assertOk();
        $export = $r->json('table.export');
        $this->assertNotEmpty($export);
        $this->actingAs($admin)->get($export)->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($this->makeUser($tenant, 'tenant_manager'))->get($export)->assertNotFound();
    }

    public function test_ai_credit_limit_blocks_workspace_bot_and_tenant_switch(): void
    {
        [$tenant, $admin] = $this->makeTenant('WB Credits');
        $this->enableAi();
        Http::fake();
        \App\Models\AiUsageLog::create(['tenant_id' => $tenant->id, 'feature' => 'workspace_chat', 'model' => 'x', 'credits' => 500, 'status' => 'ok']);
        $r = $this->actingAs($admin)->postJson(route('ws.assistant.send'), ['message' => 'how do I add a listing'])->assertOk();
        $this->assertStringContainsString('credits', strtolower($r->json('text')));
        $this->assertStringContainsString('Property listings', $r->json('text'));
        $this->assertSame('link', $r->json('action.type'));
        $this->assertSame(route('ws.settings.plan'), $r->json('action.url'));
        Http::assertNothingSent();
        // Tenant Admin switches the assistant off.
        $tenant->setting()->update(['workspace_bot_enabled' => false]);
        $this->actingAs($admin)->postJson(route('ws.assistant.send'), ['message' => 'hi'])->assertForbidden();
        $this->actingAs($admin)->get(route('ws.dashboard'))->assertOk()->assertDontSee('data-chat-open', false);
    }
}
