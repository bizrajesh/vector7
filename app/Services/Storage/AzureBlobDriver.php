<?php

namespace App\Services\Storage;

use App\Services\AppSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Azure Blob Storage with Shared Key authorisation. */
class AzureBlobDriver implements StorageDriver
{
    private string $account;

    private string $key;

    private string $container;

    public function __construct()
    {
        $this->account = (string) AppSettings::get('storage.azure_account');
        $this->key = (string) AppSettings::get('storage.azure_key');
        $this->container = (string) AppSettings::get('storage.azure_container');
    }

    private function request(string $method, string $path, string $body = '', string $mime = ''): \Illuminate\Http\Client\Response
    {
        $blob = implode('/', array_map('rawurlencode', explode('/', $path)));
        $url = "https://{$this->account}.blob.core.windows.net/{$this->container}/$blob";
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $headers = ['x-ms-date' => $date, 'x-ms-version' => '2021-08-06'];
        if ($method === 'PUT') {
            $headers['x-ms-blob-type'] = 'BlockBlob';
        }
        ksort($headers);
        $canonHeaders = '';
        foreach ($headers as $k => $v) {
            $canonHeaders .= "$k:$v\n";
        }
        $length = $method === 'PUT' ? (string) strlen($body) : '';
        $stringToSign = implode("\n", [$method, '', '', $length, '', $method === 'PUT' ? $mime : '', '', '', '', '', '', '']).
            "\n".$canonHeaders."/{$this->account}/{$this->container}/$blob";
        $sig = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($this->key), true));
        $headers['Authorization'] = "SharedKey {$this->account}:$sig";
        $req = Http::withHeaders($headers)->timeout(60);
        if ($method === 'PUT') {
            $req = $req->withBody($body, $mime);
        }

        return $req->send($method, $url);
    }

    public function put(string $path, string $contents, string $mime): string
    {
        $r = $this->request('PUT', $path, $contents, $mime);
        if (! $r->successful()) {
            throw new RuntimeException('Azure upload failed: HTTP '.$r->status());
        }

        return $path;
    }

    public function get(string $path): string
    {
        $r = $this->request('GET', $path);
        if (! $r->successful()) {
            throw new RuntimeException('Azure download failed: HTTP '.$r->status());
        }

        return $r->body();
    }

    public function delete(string $path): void
    {
        $this->request('DELETE', $path);
    }

    public function test(): true|string
    {
        if (! $this->account || ! $this->key || ! $this->container) {
            return 'Enter account name, key and container.';
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
