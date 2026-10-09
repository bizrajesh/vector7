<?php

namespace App\Services\Storage;

use App\Services\AppSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Shared helper: OAuth2 access token for a Google service account (JWT bearer flow). */
trait GoogleServiceAccount
{
    private function googleToken(string $credentialsJson, string $scope): string
    {
        $cred = json_decode($credentialsJson, true);
        if (! is_array($cred) || empty($cred['client_email']) || empty($cred['private_key'])) {
            throw new RuntimeException('Paste the service-account JSON key.');
        }

        return Cache::remember('gtoken:'.md5($cred['client_email'].$scope), 3000, function () use ($cred, $scope) {
            $b64 = fn ($d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
            $now = time();
            $jwt = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
                'iss' => $cred['client_email'], 'scope' => $scope, 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
            ]));
            if (! openssl_sign($jwt, $sig, $cred['private_key'], 'sha256WithRSAEncryption')) {
                throw new RuntimeException('Could not sign with the service-account key.');
            }
            $r = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt.'.'.$b64($sig),
            ]);
            if (! $r->successful()) {
                throw new RuntimeException('Google auth failed: '.$r->json('error_description', 'HTTP '.$r->status()));
            }

            return $r->json('access_token');
        });
    }
}
