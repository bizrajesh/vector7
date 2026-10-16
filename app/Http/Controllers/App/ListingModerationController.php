<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Listings\ListingTypes;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Services\AuditLogger;
use App\Services\Notify;
use Illuminate\Http\Request;

/**
 * App moderation (spec 17.2 / 17.7): App Admin and Manager view all listings, hide a listing (reason emailed to
 * the tenant), review buyer reports, pick featured listings and layouts for the home page, and approve testimonials.
 */
class ListingModerationController extends Controller
{
    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['listings', 'reports', 'layouts', 'testimonials'], true) ? $request->query('tab') : 'listings';
        $data = ['tab' => $tab, 'tenants' => Tenant::orderBy('name')->pluck('name', 'id')];
        if ($tab === 'listings') {
            $data['listings'] = Listing::with(['tenantRel:id,name', 'cover'])->withCount(['reports' => fn ($q) => $q->where('status', 'open')])
                ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', "%$s%")->orWhere('listing_code', 'like', "%$s%")->orWhere('city', 'like', "%$s%")))
                ->when($request->query('tenant'), fn ($q, $t) => $q->where('tenant_id', $t))
                ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
                ->when($request->query('status'), fn ($q, $s) => $s === 'hidden' ? $q->where('hidden_by_admin', true) : $q->where('status', $s))
                ->latest('id')->paginate(25)->withQueryString();
        } elseif ($tab === 'reports') {
            $data['reports'] = ListingReport::with(['listing.tenantRel:id,name', 'customer:id,name,email'])->when($request->query('status', 'open') !== 'all', fn ($q) => $q->where('status', $request->query('status', 'open')))
                ->latest('id')->paginate(25)->withQueryString();
        } elseif ($tab === 'layouts') {
            $data['layouts'] = Project::launched()->with('tenantRel:id,name')->withCount(['plots as available_count' => fn ($q) => $q->where('status', 'available')])
                ->orderByDesc('is_featured')->latest('launched_at')->paginate(25)->withQueryString();
        } else {
            $data['testimonials'] = Testimonial::with('tenant:id,name')->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")->latest('id')->paginate(25);
        }

        return view('app.moderation.index', $data);
    }

    public function hide(Request $request, Listing $listing)
    {
        $reason = $request->validate(['reason' => 'required|string|max:500'])['reason'];
        $listing->update(['hidden_by_admin' => true, 'hidden_reason' => $reason]);
        AuditLogger::log('listing_hidden', $listing, null, ['reason' => $reason], $listing->tenant_id);
        $tenant = Tenant::find($listing->tenant_id);
        $to = array_filter([$listing->contact?->email, $tenant?->email]);
        Notify::send('listing_hidden', $to, ['title' => $listing->title, 'code' => $listing->listing_code, 'reason' => $reason, 'link' => route('ws.listings.edit', $listing)], $listing->tenant_id);

        return back()->with('ok', 'Listing hidden and the promoter was emailed the reason.');
    }

    public function unhide(Listing $listing)
    {
        $listing->update(['hidden_by_admin' => false, 'hidden_reason' => null]);
        AuditLogger::log('listing_unhidden', $listing, null, null, $listing->tenant_id);

        return back()->with('ok', 'Listing restored to the marketplace (if it is published).');
    }

    public function feature(Request $request, string $kind, int $id)
    {
        $model = $kind === 'listing' ? Listing::findOrFail($id) : Project::findOrFail($id);
        $col = $kind === 'listing' ? 'featured' : 'is_featured';
        $model->update([$col => ! $model->{$col}]);

        return back()->with('ok', $model->{$col} ? 'Now featured on the home page.' : 'Removed from featured.');
    }

    public function resolve(Request $request, ListingReport $report)
    {
        $report->update(['status' => $request->validate(['status' => 'required|in:resolved,dismissed'])['status'], 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);

        return back()->with('ok', 'Report updated.');
    }

    public function testimonial(Request $request, Testimonial $testimonial)
    {
        $testimonial->update(['status' => $request->validate(['status' => 'required|in:approved,rejected,pending'])['status'], 'moderated_by' => $request->user()->id]);

        return back()->with('ok', 'Testimonial '.$testimonial->status.'.');
    }

    public function storeTestimonial(Request $request)
    {
        Testimonial::create($request->validate(['name' => 'required|string|max:100', 'place' => 'nullable|string|max:100', 'body' => 'required|string|max:1000',
            'rating' => 'nullable|integer|min:1|max:5']) + ['status' => 'pending']);

        return back()->with('ok', 'Testimonial added as pending — approve it once you have confirmed it with the buyer.');
    }

    public static function types(): array
    {
        return ListingTypes::options();
    }
}
