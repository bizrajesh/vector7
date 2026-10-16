<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Images and media for listings and quick-launch layouts (spec 17.3).
 * 3–20 images, JPG/PNG/WebP up to 5 MB each. On upload: 1600 px wide + 400 px thumbnail, converted to WebP,
 * EXIF (incl. GPS location) removed by re-encoding, optional small "vector7" watermark (tenant setting).
 * Stored through FileStore so the files count toward the tenant's storage.
 */
class PropertyMedia
{
    public const MAX_KB = 5120;

    public const MAX_IMAGES = 20;

    public const MIN_IMAGES = 3;

    public const MAIN_WIDTH = 1600;

    public const THUMB_WIDTH = 400;

    public static function rules(): array
    {
        return ['nullable', 'array', 'max:'.self::MAX_IMAGES];
    }

    public static function imageRules(): array
    {
        return ['file', 'max:'.self::MAX_KB, 'extensions:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp'];
    }

    /** Most images an owner may have: 20, or the plan's "images per listing" when lower. */
    public static function maxFor(Listing|Project $owner): int
    {
        $limit = Tenant::find($owner->tenant_id)?->plan()?->limit('images_per_listing') ?? self::MAX_IMAGES;

        return $limit < 0 ? self::MAX_IMAGES : max(1, min(self::MAX_IMAGES, $limit ?: self::MAX_IMAGES));
    }

    /**
     * @param  UploadedFile[]  $files
     * @return ListingImage[]
     */
    public static function addImages(Listing|Project $owner, array $files, array $captions = []): array
    {
        $tenant = Tenant::findOrFail($owner->tenant_id);
        $existing = $owner->images()->count();
        $max = self::maxFor($owner);
        if ($existing + count($files) > $max) {
            throw ValidationException::withMessages(['images' => "You can have up to $max images here ($existing already uploaded)."]);
        }
        $watermark = (bool) $tenant->setting()->image_watermark;
        $out = [];
        $sort = (int) $owner->images()->max('sort');
        foreach (array_values($files) as $i => $file) {
            [$main, $thumb, $w, $h] = self::process((string) file_get_contents($file->getRealPath()), $watermark);
            PlanLimiter::ensureStorage($tenant, strlen($main) + strlen($thumb));
            $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.webp';
            $mainFile = FileStore::storeContents($main, $name, 'image/webp', 'photo', $tenant->id, $owner, 'webp');
            $thumbFile = FileStore::storeContents($thumb, 'thumb-'.$name, 'image/webp', 'photo-thumb', $tenant->id, $owner, 'webp');
            $out[] = ListingImage::create([
                'tenant_id' => $tenant->id,
                'listing_id' => $owner instanceof Listing ? $owner->id : null,
                'project_id' => $owner instanceof Project ? $owner->id : null,
                'file_id' => $mainFile->id, 'thumb_file_id' => $thumbFile->id, 'width' => $w, 'height' => $h,
                'caption' => isset($captions[$i]) ? mb_substr(trim((string) $captions[$i]), 0, 150) : null,
                'sort' => ++$sort,
                'is_cover' => $existing === 0 && $i === 0,
            ]);
        }

        return $out;
    }

    /**
     * Resize / convert / strip EXIF / watermark. Returns [main webp bytes, thumb webp bytes, width, height].
     */
    public static function process(string $bytes, bool $watermark = false): array
    {
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            throw ValidationException::withMessages(['images' => 'One of the files is not a readable JPG, PNG or WebP image.']);
        }
        $src = self::orient($src, $bytes);
        $main = self::resize($src, self::MAIN_WIDTH);
        if ($watermark) {
            self::watermark($main);
        }
        $thumb = self::resize($src, self::THUMB_WIDTH);
        $w = imagesx($main);
        $h = imagesy($main);
        $out = [self::webp($main, 82), self::webp($thumb, 76), $w, $h];
        imagedestroy($src);
        imagedestroy($main);
        imagedestroy($thumb);

        return $out;
    }

    /** Apply the JPEG EXIF orientation before the EXIF data is discarded. */
    private static function orient(\GdImage $img, string $bytes): \GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($bytes, "\xFF\xD8")) {
            return $img;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $o = (int) ($exif['Orientation'] ?? 1);
        $rotated = match ($o) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => null,
        };
        if ($rotated) {
            imagedestroy($img);

            return $rotated;
        }

        return $img;
    }

    private static function resize(\GdImage $src, int $maxW): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $nw = min($w, $maxW);
        $nh = max(1, (int) round($h * $nw / $w));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagealphablending($dst, true);

        return $dst;
    }

    private static function watermark(\GdImage $img): void
    {
        $path = public_path('images/brand/watermark.png');
        $mark = is_file($path) ? @imagecreatefrompng($path) : false;
        if (! $mark) {
            return;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $mw = max(60, (int) round($w * 0.14));
        $mh = (int) round(imagesy($mark) * $mw / imagesx($mark));
        $margin = max(8, (int) round($w * 0.015));
        imagealphablending($img, true);
        imagecopyresampled($img, $mark, $w - $mw - $margin, $h - $mh - $margin, 0, 0, $mw, $mh, imagesx($mark), imagesy($mark));
        imagedestroy($mark);
    }

    private static function webp(\GdImage $img, int $quality): string
    {
        imagesavealpha($img, true);
        ob_start();
        imagewebp($img, null, $quality);

        return (string) ob_get_clean();
    }

    public static function reorder(Listing|Project $owner, array $ids): void
    {
        DB::transaction(function () use ($owner, $ids) {
            foreach (array_values($ids) as $i => $id) {
                $owner->images()->whereKey((int) $id)->update(['sort' => $i + 1]);
            }
        });
    }

    public static function setCover(Listing|Project $owner, ListingImage $image): void
    {
        DB::transaction(function () use ($owner, $image) {
            $owner->images()->update(['is_cover' => false]);
            $image->update(['is_cover' => true]);
        });
    }

    public static function delete(ListingImage $image): void
    {
        $wasCover = $image->is_cover;
        $owner = $image->listing_id ? Listing::find($image->listing_id) : Project::find($image->project_id);
        FileStore::delete($image->file);
        FileStore::delete($image->thumb);
        $image->delete();
        if ($wasCover && $owner && ($first = $owner->images()->first())) {
            $first->update(['is_cover' => true]);
        }
    }

    /** Optional brochure PDF (max 10 MB). */
    public static function brochure(Listing|Project $owner, UploadedFile $file): void
    {
        $old = $owner->brochure;
        $f = FileStore::store($file, 'brochure', $owner->tenant_id, $owner, 'layout');
        if ($f->mime !== 'application/pdf') {
            FileStore::delete($f);
            throw ValidationException::withMessages(['brochure' => 'The brochure must be a PDF.']);
        }
        $owner->update(['brochure_file_id' => $f->id]);
        FileStore::delete($old);
    }

    /** YouTube link → canonical watch URL, or null when not a YouTube link. */
    public static function youtube(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return 'https://www.youtube.com/watch?v='.$m[1];
        }

        return null;
    }

    public static function youtubeId(?string $url): ?string
    {
        return $url && preg_match('~v=([A-Za-z0-9_-]{11})~', $url, $m) ? $m[1] : null;
    }

    /**
     * Optional AI image check (Claude vision): flags screenshots, phone numbers or another site's watermark
     * written on the photo. Returns the number of images with a warning.
     */
    public static function aiCheck(Listing|Project $owner): int
    {
        $tenant = Tenant::findOrFail($owner->tenant_id);
        $flagged = 0;
        foreach ($owner->images()->with('thumb')->get() as $img) {
            $bytes = $img->thumb ? FileStore::contents($img->thumb) : FileStore::contents($img->file);
            $reply = ClaudeClient::ask('image_check',
                'You check property photos before they go on a real-estate marketplace. Does this image look like a screenshot, '
                .'or show a phone number, or show another website\'s watermark / logo? Reply ONLY JSON: {"ok": true} or {"ok": false, "warning": "short reason"}.',
                null, [['data' => base64_encode($bytes), 'media_type' => 'image/webp']], 120, $tenant);
            $j = ClaudeClient::json($reply);
            $warning = is_array($j) && ($j['ok'] ?? true) === false ? mb_substr((string) ($j['warning'] ?? 'Check this image'), 0, 290) : null;
            $img->update(['ai_warning' => $warning]);
            $flagged += $warning ? 1 : 0;
        }

        return $flagged;
    }
}
