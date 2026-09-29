<?php

namespace App\Services;

use App\Support\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private, tenant-partitioned file storage (storage/app/private/tenants/{id}/...).
 * Files are never web-accessible directly; downloads go through an authorised
 * controller action. Stored names are random (no user-controlled paths).
 */
class FileVault
{
    public function __construct(private readonly TenantContext $context) {}

    public function store(UploadedFile $file, string $folder): string
    {
        $tenantId = $this->context->id();
        abort_unless($tenantId, 403);

        $folder = preg_replace('/[^a-z0-9_\-]/i', '', $folder);

        return $file->store("tenants/{$tenantId}/{$folder}", 'local');
    }

    public function download(?string $path, string $downloadName): StreamedResponse
    {
        $tenantId = $this->context->id();
        abort_unless($path && $tenantId && str_starts_with($path, "tenants/{$tenantId}/"), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        $safeName = preg_replace('/[^A-Za-z0-9._\- ]/', '_', $downloadName);

        return Storage::disk('local')->download($path, $safeName, [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function delete(?string $path): void
    {
        $tenantId = $this->context->id();
        if ($path && $tenantId && str_starts_with($path, "tenants/{$tenantId}/")) {
            Storage::disk('local')->delete($path);
        }
    }
}
