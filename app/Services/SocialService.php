<?php

namespace App\Services;

use App\Models\Project;
use App\Models\PromoCode;
use App\Models\SocialAccount;
use App\Models\SocialMetric;
use App\Models\SocialPost;
use App\Models\Tenant;
use App\Support\Format;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Social posts written with Claude (caption, hashtags, image suggestion) and published on schedule through
 * the platforms' official APIs (Meta Graph for Facebook & Instagram; YouTube Data API for channel metrics).
 * Not connected → the post stays a draft marked "Not connected".
 */
class SocialService
{
    public const PLATFORMS = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube'];

    public const GRAPH = 'https://graph.facebook.com/v21.0';

    /** @return array{caption:string, hashtags:string, image_suggestion:string} */
    public static function write(Project $project, string $platform, ?PromoCode $promo, ?Tenant $tenant, string $brief = ''): array
    {
        $plots = $project->plots->where('status', 'available');
        $facts = "Project: {$project->name}, {$project->location}, {$project->district}. {$project->approval_type} approved. "
            .$plots->count().' plots available'.($plots->isNotEmpty() ? ' from '.Format::inr($plots->min(fn ($p) => $p->currentPrice())).', sizes '.Format::num($plots->min('size_sqft')).'–'.Format::num($plots->max('size_sqft')).' sq ft' : '').'. '
            .'Facilities: '.implode(', ', $project->facilities ?? []).'. Link: '.$project->publicUrl().'.'
            .($promo ? " Promo code {$promo->code}: ".($promo->discount_type === 'percent' ? (float) $promo->value.'% off' : Format::inr($promo->value).' off').' till '.$promo->valid_to->format('d M Y').'.' : '');
        if (ClaudeClient::available()) {
            $reply = ClaudeClient::ask('social_post', "Write a {$platform} post for this residential plot layout in Tamil Nadu, India. "
                .'Friendly, factual, no exaggerated claims, no invented distances. '.($platform === 'youtube' ? 'Write a video title + description. ' : 'Max 120 words. ')
                ."$brief\nFacts: $facts\nReturn ONLY JSON: {\"caption\": string, \"hashtags\": string (8-12 hashtags separated by spaces), \"image_suggestion\": string (which photo or layout view to use)}.", null, [], 800, $tenant);
            $j = ClaudeClient::json($reply);
            if (is_array($j) && ! empty($j['caption'])) {
                return ['caption' => trim($j['caption']), 'hashtags' => trim((string) ($j['hashtags'] ?? '')), 'image_suggestion' => trim((string) ($j['image_suggestion'] ?? ''))];
            }
        }
        // Built-in template when AI is not configured.
        $caption = "New launch: {$project->name}, {$project->location}! {$project->approval_type}-approved residential plots"
            .($plots->isNotEmpty() ? ' from '.Format::inrShort($plots->min(fn ($p) => $p->currentPrice())).', '.$plots->count().' plots available now' : '').'. '
            .(empty($project->facilities) ? '' : 'With '.Str::lower(implode(', ', array_slice($project->facilities, 0, 4))).'. ')
            .($promo ? "Use code {$promo->code} for ".($promo->discount_type === 'percent' ? (float) $promo->value.'% off' : Format::inr($promo->value).' off').' till '.$promo->valid_to->format('d M').'. ' : '')
            .'See the live plot map and book: '.$project->publicUrl();
        $tags = '#'.Str::studly($project->location).'Plots #'.Str::studly($project->district).' #ApprovedPlots #DTCPApproved #PlotsForSale #RealEstateIndia #TamilNaduRealEstate #vector7';

        return ['caption' => $caption, 'hashtags' => $tags, 'image_suggestion' => 'Layout plan with available plots highlighted'];
    }

    public static function account(?int $tenantId, string $platform): ?SocialAccount
    {
        return SocialAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('platform', $platform)->where('is_connected', true)->first();
    }

    /** Publish now. Returns true on success; failures are stored on the post. */
    public static function publish(SocialPost $post): bool
    {
        $acc = self::account($post->tenant_id, $post->platform);
        if (! $acc) {
            $post->update(['status' => 'draft', 'error' => 'Not connected']);

            return false;
        }
        $message = trim($post->caption."\n\n".$post->hashtags);
        $link = $post->project?->status === 'launched' ? $post->project->publicUrl() : null;
        try {
            if ($post->platform === 'facebook') {
                $r = Http::asForm()->timeout(30)->post(self::GRAPH."/{$acc->account_id}/feed", array_filter(['message' => $message, 'link' => $link, 'access_token' => $acc->access_token]));
                $id = $r->json('id');
            } elseif ($post->platform === 'instagram') {
                $image = $post->project?->layoutUrl();
                if (! $image || ! $link) {
                    throw new \RuntimeException('Instagram needs a launched project with a layout picture.');
                }
                $c = Http::asForm()->timeout(30)->post(self::GRAPH."/{$acc->account_id}/media", ['image_url' => $image, 'caption' => $message, 'access_token' => $acc->access_token]);
                if (! $c->successful()) {
                    throw new \RuntimeException($c->json('error.message', 'Instagram container failed'));
                }
                $r = Http::asForm()->timeout(30)->post(self::GRAPH."/{$acc->account_id}/media_publish", ['creation_id' => $c->json('id'), 'access_token' => $acc->access_token]);
                $id = $r->json('id');
            } else {
                throw new \RuntimeException('YouTube publishes videos only — upload the video in YouTube Studio and paste this text as its description.');
            }
            if (! $r->successful() || ! $id) {
                throw new \RuntimeException($r->json('error.message', 'HTTP '.$r->status()));
            }
            $post->update(['status' => 'published', 'published_at' => now(), 'external_id' => $id, 'error' => null]);

            return true;
        } catch (\Throwable $e) {
            $post->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 480)]);

            return false;
        }
    }

    /** Scheduler: publish posts whose time has come. */
    public static function publishDue(): int
    {
        $n = 0;
        foreach (SocialPost::withoutGlobalScopes()->where('status', 'scheduled')->where('scheduled_at', '<=', now())->with(['project' => fn ($q) => $q->withoutGlobalScopes()])->get() as $post) {
            $n += self::publish($post) ? 1 : 0;
        }

        return $n;
    }

    /** Scheduler: daily reach / likes / comments / followers for connected App accounts. */
    public static function pullMetrics(): void
    {
        foreach (SocialAccount::withoutGlobalScopes()->whereNull('tenant_id')->where('is_connected', true)->get() as $acc) {
            try {
                $row = ['reach' => 0, 'likes' => 0, 'comments' => 0, 'followers' => 0];
                if ($acc->platform === 'youtube') {
                    $r = Http::timeout(20)->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'statistics', 'id' => $acc->account_id, 'key' => $acc->access_token]);
                    $st = $r->json('items.0.statistics', []);
                    $row = ['reach' => (int) ($st['viewCount'] ?? 0), 'likes' => 0, 'comments' => 0, 'followers' => (int) ($st['subscriberCount'] ?? 0)];
                } elseif ($acc->platform === 'instagram') {
                    $r = Http::timeout(20)->get(self::GRAPH."/{$acc->account_id}", ['fields' => 'followers_count,media.limit(25){like_count,comments_count}', 'access_token' => $acc->access_token]);
                    $media = collect($r->json('media.data', []));
                    $row = ['reach' => 0, 'likes' => (int) $media->sum('like_count'), 'comments' => (int) $media->sum('comments_count'), 'followers' => (int) $r->json('followers_count', 0)];
                } else {
                    $r = Http::timeout(20)->get(self::GRAPH."/{$acc->account_id}", ['fields' => 'followers_count,fan_count,posts.limit(25){reactions.summary(true),comments.summary(true)}', 'access_token' => $acc->access_token]);
                    $posts = collect($r->json('posts.data', []));
                    $row = ['reach' => 0, 'likes' => (int) $posts->sum(fn ($p) => $p['reactions']['summary']['total_count'] ?? 0), 'comments' => (int) $posts->sum(fn ($p) => $p['comments']['summary']['total_count'] ?? 0), 'followers' => (int) $r->json('followers_count', $r->json('fan_count', 0))];
                }
                if ($r->successful()) {
                    SocialMetric::updateOrCreate(['tenant_id' => null, 'platform' => $acc->platform, 'metric_date' => today()], $row);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
