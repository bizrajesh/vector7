<?php

namespace App\Services;

use App\Exceptions\PlanLimitException;
use App\Models\AiUsageLog;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Anthropic Claude Messages API. Model and key come from App Settings (key stored encrypted).
 * Every call is logged to ai_usage_logs; tenant AI credits (plan) and the App monthly cap are enforced.
 */
class ClaudeClient
{
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public static function available(): bool
    {
        return AppSettings::get('ai.enabled', '1') === '1' && AppSettings::has('ai.api_key');
    }

    /**
     * Feature groups with their own on/off switch, daily cap and model (spec 17.11).
     * Marketplace groups are charged to the App budget; the workspace bot to the tenant's AI credits.
     */
    public const GROUPS = [
        'market_search' => ['label' => 'Marketplace AI search', 'features' => ['market_search_parse', 'market_search_rank'], 'cap' => 500],
        'market_chat' => ['label' => 'Marketplace chatbot', 'features' => ['market_chat'], 'cap' => 1000],
        'workspace_chat' => ['label' => 'Workspace chatbot', 'features' => ['workspace_chat'], 'cap' => 2000],
    ];

    public static function groupOf(string $feature): ?string
    {
        foreach (self::GROUPS as $g => $cfg) {
            if (in_array($feature, $cfg['features'], true)) {
                return $g;
            }
        }

        return null;
    }

    /** Model for a feature: the group's model if set, else the default model. */
    public static function model(?string $feature = null): string
    {
        $group = $feature ? self::groupOf($feature) : null;
        $m = $group ? trim((string) AppSettings::get("ai.model_$group", '')) : '';

        return $m !== '' ? $m : (string) AppSettings::get('ai.model', 'claude-sonnet-5-5');
    }

    /** AI is configured, the group is switched on and today's cap for the group is not reached. */
    public static function groupAvailable(string $group): bool
    {
        if (! self::available() || AppSettings::get("ai.{$group}_enabled", '1') !== '1') {
            return false;
        }
        $cap = (int) AppSettings::get("ai.{$group}_daily_cap", self::GROUPS[$group]['cap']);

        return $cap <= 0 || self::usedToday($group) < $cap;
    }

    public static function usedToday(string $group): int
    {
        return (int) AiUsageLog::whereIn('feature', self::GROUPS[$group]['features'])->where('created_at', '>=', today())->sum('credits');
    }

    /**
     * @param  array<int, array{data:string, media_type:string}>  $images  base64 images (vision)
     */
    public static function ask(string $feature, string $prompt, ?string $system = null, array $images = [], int $maxTokens = 2000, ?Tenant $tenant = null): string
    {
        $content = [];
        foreach ($images as $img) {
            $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $img['media_type'], 'data' => $img['data']]];
        }
        $content[] = ['type' => 'text', 'text' => $prompt];
        $r = self::messages($feature, [['role' => 'user', 'content' => $content]], $system, [], $maxTokens, $tenant);

        return self::text($r);
    }

    /**
     * Messages API call with optional tools (tool use). Returns the decoded response
     * (content blocks, stop_reason, usage). Enforces plan credits / caps and logs usage.
     *
     * @param  array<int, array{role:string, content:mixed}>  $messages
     * @param  array<int, array{name:string, description:string, input_schema:array}>  $tools
     */
    public static function messages(string $feature, array $messages, ?string $system = null, array $tools = [], int $maxTokens = 2000, ?Tenant $tenant = null, ?array $toolChoice = null): array
    {
        if (! self::available()) {
            throw new RuntimeException('AI is not configured. Ask the vector7 admin to add the Anthropic API key in Settings → AI.');
        }
        $group = self::groupOf($feature);
        if ($group && ! self::groupAvailable($group)) {
            throw new RuntimeException(self::GROUPS[$group]['label'].' is switched off or has reached today\'s limit.');
        }
        if ($tenant) {
            if (! PlanLimiter::moduleEnabled($tenant, 'ai')) {
                throw new PlanLimitException('AI features are not included in your plan. Upgrade your plan to use them.');
            }
            PlanLimiter::ensure($tenant, 'ai_credits');
        }
        $cap = (int) AppSettings::get('ai.monthly_cap', 0);
        if ($cap > 0 && (int) AiUsageLog::where('created_at', '>=', now()->startOfMonth())->sum('credits') >= $cap) {
            throw new RuntimeException('The monthly AI usage cap has been reached. Try again next month or ask the vector7 admin to raise it.');
        }

        $model = self::model($feature);
        $payload = ['model' => $model, 'max_tokens' => $maxTokens, 'messages' => $messages];
        if ($system) {
            $payload['system'] = $system;
        }
        if ($tools) {
            $payload['tools'] = $tools;
            if ($toolChoice) {
                $payload['tool_choice'] = $toolChoice;
            }
        }

        $log = ['tenant_id' => $tenant?->id, 'user_id' => Auth::guard('web')->id(), 'feature' => $feature, 'model' => $model, 'credits' => 1];
        try {
            $r = Http::withHeaders([
                'x-api-key' => (string) AppSettings::get('ai.api_key'),
                'anthropic-version' => '2023-06-01',
            ])->acceptJson()->timeout(120)->post(self::ENDPOINT, $payload);
        } catch (\Throwable $e) {
            AiUsageLog::create($log + ['status' => 'error', 'error' => substr($e->getMessage(), 0, 490)]);
            throw new RuntimeException('Could not reach the AI service. Please try again.');
        }
        if (! $r->successful()) {
            AiUsageLog::create($log + ['status' => 'error', 'error' => substr($r->json('error.message', 'HTTP '.$r->status()), 0, 490)]);
            throw new RuntimeException('The AI service returned an error: '.$r->json('error.message', 'HTTP '.$r->status()));
        }
        AiUsageLog::create($log + ['status' => 'ok', 'input_tokens' => (int) $r->json('usage.input_tokens'), 'output_tokens' => (int) $r->json('usage.output_tokens')]);

        return (array) $r->json();
    }

    /** Text blocks of a Messages API response joined together. */
    public static function text(array $response): string
    {
        return collect($response['content'] ?? [])->where('type', 'text')->pluck('text')->implode("\n");
    }

    /** First tool_use block's input with the given tool name (forced tool use = strict JSON). */
    public static function toolInput(array $response, string $tool): ?array
    {
        $block = collect($response['content'] ?? [])->first(fn ($b) => ($b['type'] ?? '') === 'tool_use' && ($b['name'] ?? '') === $tool);

        return $block ? (array) ($block['input'] ?? []) : null;
    }

    /** Extract the first JSON object/array from a model reply. */
    public static function json(string $text): mixed
    {
        if (preg_match('/```(?:json)?\s*(.+?)```/s', $text, $m)) {
            $text = $m[1];
        }
        $start = strcspn($text, '[{');
        $text = substr($text, $start);
        $decoded = json_decode(trim($text), true);
        if ($decoded === null) {
            // trim trailing prose after the last closing bracket
            $end = max(strrpos($text, '}') ?: 0, strrpos($text, ']') ?: 0);
            $decoded = json_decode(substr($text, 0, $end + 1), true);
        }

        return $decoded;
    }
}
