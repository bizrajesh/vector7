<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Models\CustomerShortlist;
use App\Models\Listing;
use App\Models\Project;
use App\Models\SavedSearch;
use App\Models\Testimonial;
use App\Services\PropertySearch;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Shortlist, compare (up to 3), saved searches and testimonials for signed-in buyers (spec 17.7). */
class ShortlistController extends Controller
{
    public const MAX_COMPARE = 3;

    public function toggle(Request $request)
    {
        [$kind, $id] = $this->parseKey((string) $request->input('key'));
        $customer = auth('customer')->user();
        $col = $kind === 'L' ? 'listing_id' : 'project_id';
        $exists = $kind === 'L' ? Listing::live()->whereKey($id)->exists() : Project::launched()->whereKey($id)->exists();
        abort_unless($exists, 404);
        $row = CustomerShortlist::where('customer_id', $customer->id)->where($col, $id)->first();
        if ($row) {
            $row->delete();
            $on = false;
        } else {
            abort_if(CustomerShortlist::where('customer_id', $customer->id)->count() >= 100, 422, __('Your shortlist is full (100).'));
            CustomerShortlist::create(['customer_id' => $customer->id, $col => $id]);
            $on = true;
        }
        if ($request->expectsJson()) {
            return response()->json(['on' => $on]);
        }

        return back()->with('ok', $on ? __('Added to your shortlist.') : __('Removed from your shortlist.'));
    }

    public function index()
    {
        $customer = auth('customer')->user();

        return view('account.shortlist', [
            'items' => $this->items(CustomerShortlist::where('customer_id', $customer->id)->latest('id')->get()->map(fn ($s) => $s->listing_id ? 'L:'.$s->listing_id : 'P:'.$s->project_id)->all()),
            'searches' => SavedSearch::where('customer_id', $customer->id)->latest('id')->get(),
        ]);
    }

    /** /compare?keys=L:1,P:4,L:9 — public, so a shared compare link works too. */
    public function compare(Request $request)
    {
        $keys = array_slice(array_values(array_unique(array_filter(explode(',', (string) $request->query('keys'))))), 0, self::MAX_COMPARE);
        $items = $this->items($keys);

        return view('market.compare', ['items' => $items, 'seo' => ['title' => __('Compare properties'), 'description' => __('Side-by-side comparison of properties on vector7.'), 'robots' => 'noindex,follow']]);
    }

    public function toggleAlerts(SavedSearch $search)
    {
        abort_unless($search->customer_id === auth('customer')->id(), 404);
        $search->update(['alerts' => ! $search->alerts]);

        return back()->with('ok', $search->alerts ? __('Email alerts on.') : __('Email alerts off.'));
    }

    public function destroySearch(SavedSearch $search)
    {
        abort_unless($search->customer_id === auth('customer')->id(), 404);
        $search->delete();

        return back()->with('ok', __('Saved search deleted.'));
    }

    /** Buyers can share a testimonial; it shows on the home page after App moderation. */
    public function testimonial(Request $request)
    {
        $customer = auth('customer')->user();
        $data = $request->validate(['body' => 'required|string|min:20|max:1000', 'place' => 'nullable|string|max:100', 'rating' => 'nullable|integer|min:1|max:5']);
        abort_if(Testimonial::where('customer_id', $customer->id)->where('created_at', '>', now()->subDays(30))->exists(), 422, __('You have already shared a testimonial this month — thank you!'));
        Testimonial::create($data + ['customer_id' => $customer->id, 'name' => $customer->name, 'status' => 'pending']);

        return back()->with('ok', __('Thank you! Your testimonial will appear once our team has reviewed it.'));
    }

    /** Cards for a list of keys (only live items; order kept). */
    private function items(array $keys): Collection
    {
        return app(Tenancy::class)->withoutScope(function () use ($keys) {
            $out = collect();
            foreach ($keys as $key) {
                [$kind, $id] = $this->parseKey($key, false);
                if (! $kind) {
                    continue;
                }
                if ($kind === 'L') {
                    $l = Listing::live()->with(['cover', 'contact', 'featureRows.master', 'tenantRel:id,name'])->find($id);
                    if ($l) {
                        $out->push(PropertySearch::listingItem($l) + ['details' => $l->type()->specTable($l)]);
                    }
                } else {
                    $p = Project::launched()->with(['tenantRel:id,name', 'images', 'contactBlock', 'featureRows.master', 'layoutFile'])->find($id);
                    $plots = $p ? $p->plots()->where('status', 'available')->get() : collect();
                    if ($p && $plots->isNotEmpty()) {
                        $out->push(PropertySearch::layoutItem($p, $plots) + ['details' => ['Approval' => $p->approvalBadge() ?? $p->approval_type.' approved', 'Total extent' => (float) $p->size_acres.' acres', 'Plots available' => $plots->count()]]);
                    }
                }
            }

            return $out;
        });
    }

    private function parseKey(string $key, bool $strict = true): array
    {
        if (preg_match('/^([LP]):(\d+)$/', $key, $m)) {
            return [$m[1], (int) $m[2]];
        }
        abort_if($strict, 422, __('Unknown property.'));

        return [null, null];
    }
}
