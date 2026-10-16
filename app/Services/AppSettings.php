<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Global App settings (integrations, organisation, SEO). Secrets are stored encrypted.
 */
class AppSettings
{
    public const SECRET_KEYS = [
        'ai.api_key', 'payment.razorpay_key_secret', 'payment.razorpay_webhook_secret', 'payment.swipe_api_key', 'payment.swipe_webhook_secret',
        'smtp.password', 'storage.s3_secret', 'storage.gcs_credentials', 'storage.azure_key', 'storage.gdrive_credentials',
    ];

    public const DEFAULTS = [
        'org.name' => 'vector7',
        'org.tagline' => 'Realty Manage Portal',
        'org.address' => 'Thanjavur, Tamil Nadu, India',
        'org.contact' => '',
        'org.support_email' => 'support@vector7.in',
        'org.website' => 'https://vector7.in',
        'org.instagram' => '',
        'org.facebook' => '',
        'org.youtube' => '',
        'org.linkedin' => '',
        'ai.model' => 'claude-sonnet-5-5',
        'ai.monthly_cap' => '2000',
        'ai.enabled' => '1',
        'ai.market_search_enabled' => '1',
        'ai.market_search_daily_cap' => '500',
        'ai.model_market_search' => '',
        'ai.market_chat_enabled' => '1',
        'ai.market_chat_daily_cap' => '1000',
        'ai.model_market_chat' => '',
        'ai.workspace_chat_enabled' => '1',
        'ai.workspace_chat_daily_cap' => '2000',
        'ai.model_workspace_chat' => '',
        'ai.bot_name' => 'Vee',
        'ai.bot_greeting' => 'Hi! I am Vee, the vector7 assistant. Tell me what property you are looking for, or ask me about patta, EC, DTCP or how booking works. வணக்கம்! தமிழிலும் கேட்கலாம்.',
        'ai.faq' => "Is it free for buyers?\nBrowsing, requesting a call back, enquiring and booking are free. Seeing an owner's phone number straight away costs a small fee (shown on the property), paid online to vector7.\n\nHow do I book a plot?\nOpen a layout, choose an available plot, tap Book now, sign in and accept the booking terms. Pay the first instalment to the promoter within the booking validity period.\n\nWho do I pay?\nPayments are made directly to the promoter (cash, cheque, bank transfer or UPI). vector7 does not collect payments from buyers.",
        'payment.gateway' => 'none',
        'contact.charge_enabled' => '1',
        'contact.charge_listing' => '49',
        'contact.charge_layout' => '49',
        'contact.validity_days' => '30',
        'listings.free' => '5',
        'listings.image_max_mb' => '5',
        'payment.test_mode' => '1',
        'storage.driver' => 'local',
        'security.two_factor' => '0',
        'seo.ai_crawlers' => 'allow',
        'seo.default_title' => 'vector7 — Approved residential plots from trusted promoters',
        'seo.default_description' => 'Browse DTCP and panchayat approved residential plot layouts, see live plot availability, offers and book online with vector7.',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return $all[$key] ?? self::DEFAULTS[$key] ?? $default;
    }

    public static function all(): array
    {
        return Cache::remember('app_settings', 300, function () {
            $out = [];
            foreach (Setting::all() as $s) {
                try {
                    $out[$s->key] = $s->is_encrypted && $s->value !== null ? Crypt::decryptString($s->value) : $s->value;
                } catch (\Throwable) {
                    $out[$s->key] = null;
                }
            }

            return $out;
        });
    }

    public static function set(string $key, ?string $value): void
    {
        $secret = in_array($key, self::SECRET_KEYS, true);
        Setting::updateOrCreate(['key' => $key], [
            'value' => $secret && $value !== null && $value !== '' ? Crypt::encryptString($value) : $value,
            'is_encrypted' => $secret,
        ]);
        Cache::forget('app_settings');
    }

    public static function has(string $key): bool
    {
        return filled(self::get($key));
    }

    /** "••••last4" for showing a stored secret without revealing it. */
    public static function masked(string $key): string
    {
        $v = (string) self::get($key, '');

        return $v === '' ? '' : str_repeat('•', 8).substr($v, -4);
    }
}
