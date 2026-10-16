<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Project;
use App\Services\FileStore;
use App\Services\PropertyMedia;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Photos, brochure and video for listings (kind = listing) and layouts (kind = project) — spec 17.3.
 * Listings need listings.update; layouts need launch.update (Sales and Support are read-only).
 */
class MediaController extends Controller
{
    private function owner(Request $request, string $kind, int $id): Listing|Project
    {
        $owner = match ($kind) {
            'listing' => Listing::findOrFail($id),
            'project' => Project::findOrFail($id),
            default => abort(404),
        };
        abort_unless($request->user()->hasPerm($kind === 'listing' ? 'listings.update' : 'launch.update'), 403);

        return $owner;
    }

    private function image(Listing|Project $owner, int $image): ListingImage
    {
        return $owner->images()->whereKey($image)->firstOrFail();
    }

    private function back(string $kind, Listing|Project $owner)
    {
        return redirect()->to(($kind === 'listing' ? route('ws.listings.edit', $owner) : route('ws.launch.show', $owner)).'#media');
    }

    public function upload(Request $request, string $kind, int $id)
    {
        $owner = $this->owner($request, $kind, $id);
        $request->validate(['images' => ['required', 'array', 'max:'.PropertyMedia::MAX_IMAGES], 'images.*' => PropertyMedia::imageRules()],
            ['images.*.max' => 'Each photo can be up to '.(PropertyMedia::maxKb() / 1024).' MB.', 'images.*.extensions' => 'Use JPG, PNG or WebP photos.']);
        $n = count(PropertyMedia::addImages($owner, $request->file('images')));

        return $this->back($kind, $owner)->with('ok', "$n photo(s) added.");
    }

    /** Reorder: JSON {ids: [...]} from drag and drop, or a form "move" = "<id>:up|down" (keyboard). */
    public function order(Request $request, string $kind, int $id)
    {
        $owner = $this->owner($request, $kind, $id);
        $ids = $owner->images()->pluck('id')->all();
        if ($request->filled('move') && preg_match('/^(\d+):(up|down)$/', (string) $request->input('move'), $m)) {
            $i = array_search((int) $m[1], $ids, true);
            $j = $m[2] === 'up' ? $i - 1 : $i + 1;
            if ($i !== false && isset($ids[$j])) {
                [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            }
        } else {
            $new = array_values(array_intersect(array_map('intval', (array) $request->input('ids', [])), $ids));
            if (count($new) !== count($ids)) {
                return response()->json(['ok' => false], 422);
            }
            $ids = $new;
        }
        // Cover stays first; the rest follow the chosen order.
        PropertyMedia::reorder($owner, $ids);

        return $request->expectsJson() ? response()->json(['ok' => true]) : $this->back($kind, $owner);
    }

    public function cover(Request $request, string $kind, int $id, int $image)
    {
        $owner = $this->owner($request, $kind, $id);
        PropertyMedia::setCover($owner, $this->image($owner, $image));

        return $this->back($kind, $owner)->with('ok', 'Cover photo set.');
    }

    public function caption(Request $request, string $kind, int $id, int $image)
    {
        $owner = $this->owner($request, $kind, $id);
        $this->image($owner, $image)->update($request->validate(['caption' => 'nullable|string|max:150']));

        return $this->back($kind, $owner)->with('ok', 'Caption saved.');
    }

    public function destroy(Request $request, string $kind, int $id, int $image)
    {
        $owner = $this->owner($request, $kind, $id);
        $img = $this->image($owner, $image);
        if ($owner instanceof Listing && $owner->isLive() && $owner->images()->count() <= PropertyMedia::MIN_IMAGES) {
            return $this->back($kind, $owner)->with('error', 'A published listing needs at least '.PropertyMedia::MIN_IMAGES.' photos. Add another photo first, or unpublish the listing.');
        }
        PropertyMedia::delete($img);

        return $this->back($kind, $owner)->with('ok', 'Photo deleted.');
    }

    public function brochure(Request $request, string $kind, int $id)
    {
        $owner = $this->owner($request, $kind, $id);
        $request->validate(['brochure' => ['required', 'file', 'max:10240', 'extensions:pdf', 'mimetypes:application/pdf']]);
        PropertyMedia::brochure($owner, $request->file('brochure'));

        return $this->back($kind, $owner)->with('ok', 'Brochure uploaded.');
    }

    public function removeBrochure(Request $request, string $kind, int $id)
    {
        $owner = $this->owner($request, $kind, $id);
        $old = $owner->brochure;
        $owner->update(['brochure_file_id' => null]);
        FileStore::delete($old);

        return $this->back($kind, $owner)->with('ok', 'Brochure removed.');
    }

    public function youtube(Request $request, string $kind, int $id)
    {
        $owner = $this->owner($request, $kind, $id);
        $url = $request->validate(['youtube_url' => 'nullable|url|max:255'])['youtube_url'] ?? null;
        $norm = PropertyMedia::youtube($url);
        if ($url && ! $norm) {
            throw ValidationException::withMessages(['youtube_url' => 'Enter a YouTube video link.']);
        }
        $owner->update(['youtube_url' => $norm]);

        return $this->back($kind, $owner)->with('ok', $norm ? 'Video link saved.' : 'Video link removed.');
    }

    public function aiCheck(Request $request, string $kind, int $id)
    {
        $owner = $this->owner($request, $kind, $id);
        try {
            $n = PropertyMedia::aiCheck($owner);
        } catch (\Throwable $e) {
            return $this->back($kind, $owner)->with('error', $e->getMessage());
        }

        return $this->back($kind, $owner)->with($n ? 'error' : 'ok', $n ? "$n photo(s) may be a screenshot or show a phone number / another site's watermark — see the notes under each photo." : 'All photos look fine.');
    }
}
