<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Listings\ListingTypes;
use App\Models\FeatureMaster;
use App\Models\Lead;
use App\Models\Listing;
use App\Services\IdGenerator;
use App\Services\PlanLimiter;
use App\Services\PropertyDetails;
use App\Services\PropertyMedia;
use App\Services\SeoService;
use App\Support\Excel;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Individual property listings (spec 17.2): plot, flat, farm land, commercial space, villa, independent house.
 * Tenant Admin and Manager create, edit, publish, unpublish and (soft) delete; Sales and Support view only
 * and work the leads. Listings are enquiry / lead based — online booking stays for layout plots.
 */
class ListingController extends Controller
{
    public function index(Request $request)
    {
        $q = Listing::with(['cover', 'contact'])->withCount('images')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', "%$s%")->orWhere('listing_code', 'like', "%$s%")->orWhere('city', 'like', "%$s%")))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('id');
        if ($request->query('export') === 'xlsx' && $request->user()->hasPerm('listings.export')) {
            return Excel::download('listings.xlsx', ['Listing ID', 'Type', 'Purpose', 'Title', 'City', 'District', 'Price', 'Offer price', 'Offer valid till', 'Area (sq ft)', 'BHK', 'Status', 'Published', 'Views'],
                $q->get()->map(fn ($l) => [$l->listing_code, $l->typeLabel(), $l->purpose, $l->title, $l->city, $l->district, (float) $l->price, $l->offer_price ? (float) $l->offer_price : null,
                    $l->offer_valid_till, $l->area_sqft ? (float) $l->area_sqft : null, $l->bhk, $l->statusLabel(), $l->published_at, $l->views]), 'Listings');
        }
        $leadCounts = \App\Models\LeadInterest::whereNotNull('listing_id')->selectRaw('listing_id, count(distinct lead_id) n')->groupBy('listing_id')->pluck('n', 'listing_id');

        return view('ws.listings.index', [
            'listings' => $q->paginate(20)->withQueryString(),
            'leadCounts' => $leadCounts,
            'usage' => PlanLimiter::usage(app(Tenancy::class)->get())['listings'],
        ]);
    }

    public function create(Request $request)
    {
        $type = $request->query('type');
        if (! isset(ListingTypes::CLASSES[$type])) {
            return view('ws.listings.choose-type', ['types' => ListingTypes::all()]);
        }

        return $this->form(new Listing(['type' => $type, 'purpose' => 'sale', 'state' => 'Tamil Nadu']));
    }

    public function store(Request $request)
    {
        $type = (string) $request->input('type');
        abort_unless(isset(ListingTypes::CLASSES[$type]), 422);
        $tenant = app(Tenancy::class)->get();
        [$data, $contact] = $this->validated($request, $type);
        $listing = DB::transaction(function () use ($data, $contact, $tenant, $request, $type) {
            $listing = Listing::create($data + [
                'tenant_id' => $tenant->id,
                'listing_code' => IdGenerator::next($tenant->id, 'listing'),
                'slug' => 'tmp-'.Str::random(20),
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
            $listing->update(['slug' => $this->slug($listing)]);
            PropertyDetails::saveContact($listing, $contact);
            PropertyDetails::syncFeatures($listing, (array) $request->input('features', []), (array) $request->input('highlights', []), $type);
            if ($request->hasFile('images')) {
                PropertyMedia::addImages($listing, $request->file('images'));
            }

            return $listing;
        });

        return redirect()->route('ws.listings.edit', $listing)->with('ok', 'Listing '.$listing->listing_code.' saved as a draft. Add photos (at least '.PropertyMedia::MIN_IMAGES.'), then Publish.');
    }

    public function show(Listing $listing)
    {
        $listing->load(['images', 'featureRows', 'contact', 'reports' => fn ($q) => $q->latest('id')]);
        $leads = Lead::visibleTo(request()->user())->whereHas('interests', fn ($q) => $q->where('listing_id', $listing->id))->with('stage')->latest('id')->take(25)->get();

        return view('ws.listings.show', ['listing' => $listing, 'leads' => $leads, 'problems' => $listing->publishProblems()]);
    }

    public function edit(Listing $listing)
    {
        return $this->form($listing);
    }

    public function update(Request $request, Listing $listing)
    {
        [$data, $contact] = $this->validated($request, $listing->type, $listing);
        DB::transaction(function () use ($listing, $data, $contact, $request) {
            $titleChanged = $data['title'] !== $listing->title || $data['city'] !== $listing->city;
            $listing->update($data + ['updated_by' => $request->user()->id]);
            if ($titleChanged && ! $listing->published_at) {
                $listing->update(['slug' => $this->slug($listing)]);
            }
            PropertyDetails::saveContact($listing, $contact);
            PropertyDetails::syncFeatures($listing, (array) $request->input('features', []), (array) $request->input('highlights', []), $listing->type);
        });
        if ($listing->isLive() && ($problems = $listing->fresh()->publishProblems())) {
            $listing->update(['status' => 'unpublished']);

            return redirect()->route('ws.listings.edit', $listing)->with('error', 'Saved, but the listing was unpublished: '.implode(' ', $problems));
        }
        if ($listing->isLive()) {
            SeoService::refreshListing($listing->fresh());
        }

        return redirect()->route('ws.listings.edit', $listing)->with('ok', 'Listing saved.');
    }

    /** Publish, unpublish, mark Under offer or Sold / Rented. */
    public function status(Request $request, Listing $listing)
    {
        $to = $request->validate(['status' => ['required', Rule::in(['published', 'unpublished', 'under_offer', 'sold'])]])['status'];
        if (in_array($to, Listing::LIVE, true)) {
            if ($listing->hidden_by_admin) {
                return back()->with('error', 'The vector7 team hid this listing ('.$listing->hidden_reason.'). Fix it and contact support to restore it.');
            }
            if ($problems = $listing->publishProblems()) {
                return back()->with('error', 'Before publishing: '.implode(' ', $problems));
            }
            if (! in_array($listing->status, Listing::LIVE, true)) {
                PlanLimiter::ensure(app(Tenancy::class)->get(), 'listings');
            }
        }
        $listing->update(['status' => $to, 'published_at' => $to === 'published' && ! $listing->published_at ? now() : $listing->published_at]);
        if ($listing->isLive()) {
            SeoService::refreshListing($listing->fresh());
            \App\Jobs\MatchRequirements::dispatch(null, $listing->id);
        }
        $msg = ['published' => 'Published — the listing is live on the marketplace.', 'unpublished' => 'Unpublished — hidden from the marketplace.',
            'under_offer' => 'Marked Under offer (still visible).', 'sold' => ($listing->purpose === 'rent' ? 'Marked Rented' : 'Marked Sold').' — removed from the marketplace. Record the sale in Accounts if you want.'][$to];

        return back()->with('ok', $msg);
    }

    public function destroy(Listing $listing)
    {
        $listing->update(['status' => 'unpublished']);
        $listing->delete();

        return redirect()->route('ws.listings.index')->with('ok', 'Listing '.$listing->listing_code.' deleted.');
    }

    private function form(Listing $listing)
    {
        $tenant = app(Tenancy::class)->get();
        $contact = $listing->exists && $listing->contact ? [
            'contact_name' => $listing->contact->name, 'contact_mobile' => $listing->contact->mobile, 'contact_whatsapp' => $listing->contact->whatsapp,
            'contact_email' => $listing->contact->email, 'allow_call' => $listing->contact->allow_call, 'allow_whatsapp' => $listing->contact->allow_whatsapp, 'allow_email' => $listing->contact->allow_email,
        ] : PropertyDetails::contactDefaults($tenant);
        $rows = $listing->exists ? $listing->featureRows : collect();

        return view('ws.listings.form', [
            'listing' => $listing,
            'type' => $listing->type(),
            'features' => FeatureMaster::forType($listing->type)->get()->groupBy('group'),
            'selected' => $rows->whereNotNull('feature_master_id')->pluck('feature_master_id')->all(),
            'highlights' => $rows->whereNotNull('custom_text')->pluck('custom_text')->values()->all(),
            'contact' => $contact,
            'problems' => $listing->exists ? $listing->publishProblems() : [],
        ]);
    }

    /** @return array{0: array, 1: array} listing columns, contact */
    private function validated(Request $request, string $type, ?Listing $listing = null): array
    {
        $t = ListingTypes::get($type);
        $rules = [
            'purpose' => ['required', Rule::in($t->purposes())],
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:5000',
            'address_line1' => 'nullable|string|max:150',
            'area_name' => 'nullable|string|max:100',
            'city' => 'required|string|max:100',
            'district' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pin' => ['nullable', 'regex:/^\d{6}$/'],
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'patta_number' => [$t->requiresPatta() ? 'required_without:survey_number' : 'nullable', 'nullable', 'string', 'max:60'],
            'survey_number' => [$t->requiresPatta() ? 'required_without:patta_number' : 'nullable', 'nullable', 'string', 'max:60'],
            'approval_number' => 'nullable|string|max:60',
            'price' => 'required|numeric|min:1|max:9999999999',
            'offer_text' => 'nullable|string|max:200',
            'offer_price' => 'nullable|numeric|min:1|lt:price',
            'offer_valid_till' => 'nullable|required_with:offer_price,offer_text|date|after_or_equal:today',
            'negotiable' => 'boolean',
            'images' => ['nullable', 'array', 'max:'.PropertyMedia::MAX_IMAGES],
            'images.*' => PropertyMedia::imageRules(),
        ] + $t->rules() + PropertyDetails::featureRules() + PropertyDetails::contactRules();
        $data = $request->validate($rules, [
            'pin.regex' => 'PIN must be 6 digits.', 'offer_price.lt' => 'The offer price must be lower than the actual price.',
            'offer_valid_till.required_with' => 'Give the date the offer is valid till.', 'offer_valid_till.after_or_equal' => 'The offer end date cannot be in the past.',
            'patta_number.required_without' => 'Enter the patta number or survey number.', 'survey_number.required_without' => 'Enter the survey number or patta number.',
        ] + PropertyDetails::contactMessages(), $t->attributeNames());
        $contact = PropertyDetails::contactFromRequest($request, array_intersect_key($data, PropertyDetails::contactRules()));
        if (! PropertyDetails::hasMode($contact)) {
            throw ValidationException::withMessages(['allow_call' => 'Choose at least one mode of contact.']);
        }
        $details = $t->clean((array) $request->input('details', []));
        $cols = array_intersect_key($data, array_flip(['purpose', 'title', 'description', 'address_line1', 'area_name', 'city', 'district', 'state', 'pin', 'lat', 'lng',
            'patta_number', 'survey_number', 'approval_number', 'price', 'offer_text', 'offer_price', 'offer_valid_till']));
        if (empty($cols['offer_price'])) {
            $cols['offer_price'] = null;
            $cols['offer_valid_till'] = null;
            $cols['offer_text'] = null;
        }

        return [$cols + $t->searchColumns($details) + ['type' => $type, 'type_details' => $details, 'negotiable' => $request->boolean('negotiable')], $contact];
    }

    /** /buy/flats/thanjavur/2bhk-flat-medical-college-road-L0910202612 */
    private function slug(Listing $l): string
    {
        $slug = Str::slug(Str::limit($l->title, 80, '')).'-'.Str::lower($l->listing_code);
        $base = $slug;
        $i = 2;
        while (Listing::withoutGlobalScopes()->withTrashed()->where('slug', $slug)->where('id', '!=', $l->id)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
