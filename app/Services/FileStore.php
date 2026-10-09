<?php

namespace App\Services;

use App\Models\StorageFile;
use App\Models\Tenant;
use App\Services\Storage\AzureBlobDriver;
use App\Services\Storage\GcsDriver;
use App\Services\Storage\GoogleDriveDriver;
use App\Services\Storage\LocalDriver;
use App\Services\Storage\S3Driver;
use App\Services\Storage\StorageDriver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * All uploads go through here: allow-listed types, size limits, MIME check (by content),
 * random file names, stored outside public/ and served through permission-checked routes.
 * Every upload/delete updates the tenant's storage usage.
 */
class FileStore
{
    public const DRIVERS = ['local' => 'Local disk', 'gdrive' => 'Google Drive', 's3' => 'AWS S3', 'gcs' => 'Google Cloud Storage', 'azure' => 'Azure Blob'];

    /** extension => allowed MIME types detected from file content */
    public const TYPES = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'dxf' => ['text/plain', 'image/vnd.dxf', 'application/dxf', 'application/octet-stream', 'image/x-dxf'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    ];

    public const PRESETS = [
        'document' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'docx'],
        'image' => ['jpg', 'jpeg', 'png', 'webp'],
        'layout' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
        'extract' => ['dxf', 'pdf', 'jpg', 'jpeg', 'png', 'webp'],
        'import' => ['csv', 'xlsx'],
        'logo' => ['png', 'jpg', 'jpeg'],
    ];

    public static function driver(?string $name = null): StorageDriver
    {
        return match ($name ?? AppSettings::get('storage.driver', 'local')) {
            's3' => new S3Driver,
            'gcs' => new GcsDriver,
            'azure' => new AzureBlobDriver,
            'gdrive' => new GoogleDriveDriver,
            default => new LocalDriver,
        };
    }

    /** Laravel validation rules for an upload field. */
    public static function rules(string $preset = 'document', int $maxKb = 10240, bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'max:'.$maxKb, 'extensions:'.implode(',', self::PRESETS[$preset])];
    }

    public static function store(UploadedFile $file, string $category, ?int $tenantId, ?Model $attachable = null, string $preset = 'document'): StorageFile
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();
        if (! in_array($ext, self::PRESETS[$preset], true) || ! in_array($mime, self::TYPES[$ext] ?? [], true)) {
            throw ValidationException::withMessages(['file' => "This file type is not allowed ($ext, $mime)."]);
        }
        if ($tenantId) {
            PlanLimiter::ensureStorage(Tenant::findOrFail($tenantId), $file->getSize());
        }
        $contents = (string) file_get_contents($file->getRealPath());

        return self::storeContents($contents, $file->getClientOriginalName(), $mime, $category, $tenantId, $attachable, $ext);
    }

    public static function storeContents(string $contents, string $originalName, string $mime, string $category, ?int $tenantId, ?Model $attachable = null, ?string $ext = null): StorageFile
    {
        $ext ??= pathinfo($originalName, PATHINFO_EXTENSION) ?: 'bin';
        $driverName = AppSettings::get('storage.driver', 'local');
        $path = ($tenantId ? 'tenants/'.$tenantId : 'app').'/'.$category.'/'.now()->format('Y/m').'/'.Str::random(40).'.'.$ext;
        $stored = self::driver($driverName)->put($path, $contents, $mime);
        $user = Auth::guard('web')->user() ?? Auth::guard('customer')->user();

        $file = StorageFile::create([
            'tenant_id' => $tenantId,
            'driver' => $driverName,
            'path' => $stored,
            'original_name' => mb_substr(basename($originalName), 0, 250),
            'mime' => $mime,
            'size' => strlen($contents),
            'category' => $category,
            'attachable_type' => $attachable ? $attachable::class : null,
            'attachable_id' => $attachable?->getKey(),
            'uploaded_by_type' => $user ? (Auth::guard('web')->check() ? 'user' : 'customer') : null,
            'uploaded_by' => $user?->getAuthIdentifier(),
        ]);
        if ($tenantId) {
            DB::table('tenants')->where('id', $tenantId)->increment('storage_used_bytes', $file->size);
        }

        return $file;
    }

    public static function contents(StorageFile $file): string
    {
        return self::driver($file->driver)->get($file->path);
    }

    public static function delete(?StorageFile $file): void
    {
        if (! $file) {
            return;
        }
        try {
            self::driver($file->driver)->delete($file->path);
        } catch (\Throwable) {
            // file already gone on the remote side
        }
        if ($file->tenant_id) {
            DB::table('tenants')->where('id', $file->tenant_id)->where('storage_used_bytes', '>=', $file->size)->decrement('storage_used_bytes', $file->size);
        }
        $file->delete();
    }

    /** Nightly verification: recompute every tenant's storage from storage_files. */
    public static function recalculateUsage(): void
    {
        $sums = StorageFile::whereNotNull('tenant_id')->groupBy('tenant_id')->selectRaw('tenant_id, SUM(size) as s')->pluck('s', 'tenant_id');
        foreach (Tenant::all() as $t) {
            $t->forceFill(['storage_used_bytes' => (int) ($sums[$t->id] ?? 0)])->saveQuietly();
        }
    }
}
