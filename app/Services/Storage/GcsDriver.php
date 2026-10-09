<?php

namespace App\Services\Storage;

use App\Services\AppSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Google Cloud Storage via the JSON API. */
class GcsDriver implements StorageDriver
{
    use GoogleServiceAccount;

    private function token(): string
    {
        return $this->googleToken((string) AppSettings::get('storage.gcs_credentials'), 'https://www.googleapis.com/auth/devstorage.read_write');
    }

    private function bucket(): string
    {
        return (string) AppSettings::get('storage.gcs_bucket');
    }

    public function put(string $path, string $contents, string $mime): string
    {
        $r = Http::withToken($this->token())->withBody($contents, $mime)->timeout(60)
            ->post('https://storage.googleapis.com/upload/storage/v1/b/'.rawurlencode($this->bucket()).'/o?uploadType=media&name='.rawurlencode($path));
        if (! $r->successful()) {
            throw new RuntimeException('GCS upload failed: HTTP '.$r->status());
        }

        return $path;
    }

    public function get(string $path): string
    {
        $r = Http::withToken($this->token())->timeout(60)->get('https://storage.googleapis.com/storage/v1/b/'.rawurlencode($this->bucket()).'/o/'.rawurlencode($path).'?alt=media');
        if (! $r->successful()) {
            throw new RuntimeException('GCS download failed: HTTP '.$r->status());
        }

        return $r->body();
    }

    public function delete(string $path): void
    {
        Http::withToken($this->token())->delete('https://storage.googleapis.com/storage/v1/b/'.rawurlencode($this->bucket()).'/o/'.rawurlencode($path));
    }

    public function test(): true|string
    {
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
