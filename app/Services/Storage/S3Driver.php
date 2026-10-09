<?php

namespace App\Services\Storage;

use App\Services\AppSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** AWS S3 (or S3-compatible) through the REST API with Signature V4. */
class S3Driver implements StorageDriver
{
    private string $key;

    private string $secret;

    private string $region;

    private string $bucket;

    private string $endpoint;

    public function __construct()
    {
        $this->key = (string) AppSettings::get('storage.s3_key');
        $this->secret = (string) AppSettings::get('storage.s3_secret');
        $this->region = (string) AppSettings::get('storage.s3_region', 'ap-south-1');
        $this->bucket = (string) AppSettings::get('storage.s3_bucket');
        $this->endpoint = rtrim((string) (AppSettings::get('storage.s3_endpoint') ?: "https://s3.{$this->region}.amazonaws.com"), '/');
    }

    private function request(string $method, string $path, string $body = '', array $headers = []): \Illuminate\Http\Client\Response
    {
        $uriPath = '/'.$this->bucket.'/'.implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
        $url = $this->endpoint.$uriPath;
        $host = parse_url($this->endpoint, PHP_URL_HOST);
        $amzDate = gmdate('Ymd\THis\Z');
        $date = substr($amzDate, 0, 8);
        $payloadHash = hash('sha256', $body);
        $headers = array_change_key_case(array_merge($headers, ['host' => $host, 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $amzDate]));
        ksort($headers);
        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= $k.':'.trim($v)."\n";
        }
        $signedHeaders = implode(';', array_keys($headers));
        $canonical = implode("\n", [$method, $uriPath, '', $canonicalHeaders, $signedHeaders, $payloadHash]);
        $scope = "$date/{$this->region}/s3/aws4_request";
        $toSign = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $scope, hash('sha256', $canonical)]);
        $k = hash_hmac('sha256', $date, 'AWS4'.$this->secret, true);
        $k = hash_hmac('sha256', $this->region, $k, true);
        $k = hash_hmac('sha256', 's3', $k, true);
        $k = hash_hmac('sha256', 'aws4_request', $k, true);
        $signature = hash_hmac('sha256', $toSign, $k);
        $headers['authorization'] = "AWS4-HMAC-SHA256 Credential={$this->key}/$scope, SignedHeaders=$signedHeaders, Signature=$signature";
        unset($headers['host']);

        return Http::withHeaders($headers)->withBody($body, $headers['content-type'] ?? 'application/octet-stream')->timeout(60)->send($method, $url);
    }

    public function put(string $path, string $contents, string $mime): string
    {
        $r = $this->request('PUT', $path, $contents, ['content-type' => $mime]);
        if (! $r->successful()) {
            throw new RuntimeException('S3 upload failed: HTTP '.$r->status());
        }

        return $path;
    }

    public function get(string $path): string
    {
        $r = $this->request('GET', $path);
        if (! $r->successful()) {
            throw new RuntimeException('S3 download failed: HTTP '.$r->status());
        }

        return $r->body();
    }

    public function delete(string $path): void
    {
        $this->request('DELETE', $path);
    }

    public function test(): true|string
    {
        if (! $this->key || ! $this->secret || ! $this->bucket) {
            return 'Enter access key, secret and bucket.';
        }
        try {
            $p = 'healthcheck/'.uniqid().'.txt';
            $this->put($p, 'ok', 'text/plain');
            $ok = $this->get($p) === 'ok';
            $this->delete($p);

            return $ok ? true : 'Read-back check failed.';
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }
}
