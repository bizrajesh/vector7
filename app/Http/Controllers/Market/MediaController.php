<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Project;
use App\Services\FileStore;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/**
 * Listing / layout photos. Public when the listing is live or the layout is launched; otherwise only for staff
 * of the same workspace (drafts) and App users. Long cache: URLs carry the file id (?v=…), so a new photo gets a new URL.
 */
class MediaController extends Controller
{
    public function image(Request $request, int $image, string $size)
    {
        $img = app(Tenancy::class)->withoutScope(fn () => ListingImage::with(['file', 'thumb'])->findOrFail($image));
        $public = app(Tenancy::class)->withoutScope(function () use ($img) {
            if ($img->listing_id) {
                $l = Listing::find($img->listing_id);

                return $l && $l->isLive();
            }

            return Project::whereKey($img->project_id)->where('status', 'launched')->exists();
        });
        if (! $public) {
            $u = $request->user('web');
            abort_unless($u && ($u->isAppUser() || (int) $u->tenant_id === (int) $img->tenant_id), 404);
        }
        $file = $size === 'thumb' && $img->thumb ? $img->thumb : $img->file;
        abort_unless($file, 404);

        return response(FileStore::contents($file), 200, [
            'Content-Type' => $file->mime,
            'Cache-Control' => $public ? 'public, max-age=2592000, immutable' : 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    /** Brochure PDF of a live listing or a launched layout. */
    public function brochure(string $kind, int $id)
    {
        $owner = app(Tenancy::class)->withoutScope(fn () => $kind === 'listing' ? Listing::live()->with('brochure')->findOrFail($id) : Project::launched()->with('brochure')->findOrFail($id));
        $file = $owner->brochure ?? abort(404);
        $name = \Illuminate\Support\Str::slug($kind === 'listing' ? $owner->title : $owner->name).'-brochure.pdf';

        return response(FileStore::contents($file), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
