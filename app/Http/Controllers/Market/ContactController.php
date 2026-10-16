<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingContact;
use App\Models\Project;
use App\Services\LeadService;
use App\Services\Notify;
use App\Services\PropertyDetails;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Contact block on listing and layout pages (spec 17.5).
 *  - The number is masked (98XXX XX345). Call, WhatsApp and "Show number" ask once for name + mobile + consent;
 *    after that the visitor's session remembers them, so later clicks go straight through.
 *  - Every click and every email enquiry becomes a lead in the owner's Leads (duplicates merge by mobile / email).
 *  - Reveals are rate-limited per visitor (throttle:reveal).
 */
class ContactController extends Controller
{
    public const MODES = ['call' => 'call_click', 'whatsapp' => 'whatsapp_click', 'show' => 'show_number'];

    public function reveal(Request $request, string $kind, int $id)
    {
        [$owner, $c, $title, $code, $url] = $this->owner($kind, $id);
        $mode = $request->validate(['mode' => ['required', Rule::in(array_keys(self::MODES))]])['mode'];
        $modes = $c['modes'];
        abort_if($mode === 'whatsapp' && ! in_array('whatsapp', $modes, true), 422, 'WhatsApp is not enabled for this property.');
        abort_if(in_array($mode, ['call', 'show'], true) && ! in_array('call', $modes, true), 422, 'Calls are not enabled for this property.');

        $person = $request->session()->get('contact_person');
        $customer = auth('customer')->user();
        // Signed-in buyers with a mobile number skip the form: the number shows straight away (still logged as a lead).
        if (! $person && $customer?->mobile && ! $request->filled('mobile')) {
            $person = ['name' => $customer->name, 'mobile' => $customer->mobile];
        }
        if (! $person || $request->filled('mobile')) {
            $data = $request->validate([
                'name' => 'required|string|max:100',
                'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
                'consent' => 'accepted',
                'website' => 'nullable|max:0',
            ], ['mobile.regex' => 'Enter a 10-digit mobile number.', 'consent.accepted' => 'Please agree to share your details with the owner.']);
            $person = ['name' => $data['name'], 'mobile' => $data['mobile']];
            $request->session()->put('contact_person', $person);
        }
        $source = $request->input('src') === 'ai' ? 'ai_search' : self::MODES[$mode];
        $body = ['call' => 'Tapped Call', 'whatsapp' => 'Tapped WhatsApp', 'show' => 'Viewed the phone number'][$mode].' on '.$title.' ('.$code.').';
        LeadService::capture($owner->tenant_id, $source, $person + ['customer_id' => $customer?->id, 'email' => $customer?->email, 'consent' => true],
            LeadService::interestFromKey($kind, $owner->id), $body);

        $number = preg_replace('/\D/', '', (string) $c['mobile']);
        $wa = '91'.substr(preg_replace('/\D/', '', (string) ($c['whatsapp'] ?: $c['mobile'])), -10);
        $text = "Hi, I'm interested in {$title} ({$code}) on vector7.in – {$url}";
        $out = [
            'number' => $number, 'display' => substr($number, 0, 5).' '.substr($number, 5), 'name' => $c['name'],
            'tel' => 'tel:+91'.$number, 'whatsapp' => 'https://wa.me/'.$wa.'?text='.rawurlencode($text), 'mode' => $mode,
        ];
        if ($request->expectsJson()) {
            return response()->json($out);
        }
        if ($mode === 'whatsapp') {
            return redirect()->away($out['whatsapp']);
        }

        return back()->with('revealed', [$kind.':'.$id => $out])->with('ok', 'Contact '.$c['name'].' on '.$out['display'].'.');
    }

    /** Email enquiry form on a listing / layout. */
    public function email(Request $request, string $kind, int $id)
    {
        [$owner, $c, $title, $code, $url] = $this->owner($kind, $id);
        abort_unless(in_array('email', $c['modes'], true), 422, 'Email enquiries are not enabled for this property.');
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'message' => 'required|string|max:2000',
            'consent' => 'accepted',
            'website' => 'nullable|max:0',
        ], ['mobile.regex' => 'Enter a 10-digit mobile number.', 'consent.accepted' => 'Please agree to share your details with the owner.']);
        $customer = auth('customer')->user();
        $lead = LeadService::capture($owner->tenant_id, $request->input('src') === 'ai' ? 'ai_search' : 'enquiry', [
            'name' => $data['name'], 'email' => $data['email'], 'mobile' => $data['mobile'], 'customer_id' => $customer?->id, 'message' => $data['message'], 'consent' => true,
        ], LeadService::interestFromKey($kind, $owner->id), $data['message']);
        if ($c['email']) {
            Notify::send('enquiry_received', [$c['email']], ['name' => $data['name'], 'email' => $data['email'], 'mobile' => $data['mobile'],
                'project' => $title.' ('.$code.')', 'message' => $data['message'], 'link' => route('ws.leads.show', $lead)], $owner->tenant_id);
        }
        $request->session()->put('contact_person', ['name' => $data['name'], 'mobile' => $data['mobile']]);

        return back()->with('ok', 'Thanks! Your message was sent to '.$c['name'].'. They will contact you shortly.');
    }

    /** @return array{0: Listing|Project, 1: array, 2: string, 3: string, 4: string} */
    private function owner(string $kind, int $id): array
    {
        if ($kind === 'listing') {
            $l = Listing::live()->with('contact')->findOrFail($id);
            abort_unless($l->contact, 404);

            return [$l, self::contactArray($l->contact), $l->title, $l->listing_code, $l->publicUrl()];
        }
        abort_unless($kind === 'project', 404);
        $p = Project::launched()->with(['contactBlock', 'tenantRel'])->findOrFail($id);

        return [$p, self::projectContact($p), $p->name, $p->project_code, $p->publicUrl()];
    }

    public static function contactArray(ListingContact $c): array
    {
        return ['name' => $c->name, 'mobile' => $c->mobile, 'whatsapp' => $c->whatsapp, 'email' => $c->email, 'modes' => $c->modes()];
    }

    /** Layout contact: its own contact block, else the promoter's default contact settings. */
    public static function projectContact(Project $p): array
    {
        if ($p->contactBlock) {
            return self::contactArray($p->contactBlock);
        }
        $d = PropertyDetails::contactDefaults($p->tenantRel);
        $mobile = $p->contact ?: $d['contact_mobile'];

        return ['name' => $d['contact_name'], 'mobile' => $mobile, 'whatsapp' => $d['contact_whatsapp'] ?: $mobile, 'email' => $d['contact_email'],
            'modes' => array_values(array_filter(['call' => $d['allow_call'] && $mobile ? 'call' : null, 'whatsapp' => $d['allow_whatsapp'] && $mobile ? 'whatsapp' : null, 'email' => $d['allow_email'] ? 'email' : null]))];
    }
}
