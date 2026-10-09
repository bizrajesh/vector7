<?php

namespace App\Services\Storage;

use App\Services\AppSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Local disk (storage/app/private) — default; files never sit under public/. */
class LocalDriver implements StorageDriver
{
    public function put(string $path, string $contents, string $mime): string
    {
        Storage::disk('local')->put($path, $contents);

        return $path;
    }

    public function get(string $path): string
    {
        return (string) Storage::disk('local')->get($path);
    }

    public function delete(string $path): void
    {
        Storage::disk('local')->delete($path);
    }

    public function test(): true|string
    {
        $p = 'healthcheck/'.uniqid().'.txt';
        Storage::disk('local')->put($p, 'ok');
        $ok = Storage::disk('local')->get($p) === 'ok';
        Storage::disk('local')->delete($p);

        return $ok ? true : 'Local disk is not writable (storage/app/private).';
    }
}
