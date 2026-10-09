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

    public static function model(): string
    {
        return (string) AppSettings::get('ai.model', 'claude-sonnet-5-5');
    }

    /**
     * @param  array<int, array{data:string, media_type:string}>  $images  base64 images (vision)
     */
    public static function ask(string $feature, string $prompt, ?string $system = null, array $images = [], int $maxTokens = 2000, ?Tenant $tenant = null): string
    {
        if (! self::available()) {
            throw new RuntimeException('AI is not configured. Ask the vector7 admin to add the Anthropic API key in Settings → AI.');
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

        $content = [];
        foreach ($images as $img) {
            $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $img['media_type'], 'data' => $img['data']]];
        }
        $content[] = ['type' => 'text', 'text' => $prompt];
        $payload = ['model' => self::model(), 'max_tokens' => $maxTokens, 'messages' => [['role' => 'user', 'content' => $content]]];
        if ($system) {
            $payload['system'] = $system;
        }

        $log = ['tenant_id' => $tenant?->id, 'user_id' => Auth::guard('web')->id(), 'feature' => $feature, 'model' => self::model(), 'credits' => 1];
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

        return collect($r->json('content', []))->where('type', 'text')->pluck('text')->implode("\n");
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
