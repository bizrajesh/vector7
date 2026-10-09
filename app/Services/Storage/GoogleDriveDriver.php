<?php

namespace App\Services\Storage;

use App\Services\AppSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Google Drive (service account; share the target folder with the service-account email). Path stored = Drive file ID. */
class GoogleDriveDriver implements StorageDriver
{
    use GoogleServiceAccount;

    private function token(): string
    {
        return $this->googleToken((string) AppSettings::get('storage.gdrive_credentials'), 'https://www.googleapis.com/auth/drive');
    }

    public function put(string $path, string $contents, string $mime): string
    {
        $boundary = 'v7'.bin2hex(random_bytes(8));
        $meta = ['name' => basename($path), 'description' => $path];
        if ($folder = AppSettings::get('storage.gdrive_folder_id')) {
            $meta['parents'] = [$folder];
        }
        $body = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n".json_encode($meta)
            ."\r\n--$boundary\r\nContent-Type: $mime\r\n\r\n".$contents."\r\n--$boundary--";
        $r = Http::withToken($this->token())->withBody($body, 'multipart/related; boundary='.$boundary)->timeout(60)
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true');
        if (! $r->successful()) {
            throw new RuntimeException('Google Drive upload failed: HTTP '.$r->status());
        }

        return (string) $r->json('id');
    }

    public function get(string $path): string
    {
        $r = Http::withToken($this->token())->timeout(60)->get('https://www.googleapis.com/drive/v3/files/'.rawurlencode($path).'?alt=media&supportsAllDrives=true');
        if (! $r->successful()) {
            throw new RuntimeException('Google Drive download failed: HTTP '.$r->status());
        }

        return $r->body();
    }

    public function delete(string $path): void
    {
        Http::withToken($this->token())->delete('https://www.googleapis.com/drive/v3/files/'.rawurlencode($path).'?supportsAllDrives=true');
    }

    public function test(): true|string
    {
        try {
            $id = $this->put('healthcheck.txt', 'ok', 'text/plain');
            $ok = $this->get($id) === 'ok';
            $this->delete($id);

            return $ok ? true : 'Read-back check failed.';
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }
}
