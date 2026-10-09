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
        'payment.gateway' => 'none',
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
