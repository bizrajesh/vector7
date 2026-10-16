<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Models\ContactUnlock;
use App\Models\Lead;
use App\Models\Listing;
use App\Models\ListingContact;
use App\Models\Project;
use App\Services\ContactCharges;
use App\Services\LeadService;
use App\Services\PaymentLinks;
use App\Services\Notify;
use App\Services\PropertyDetails;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Contact block on listing and layout pages (spec 17.5, Plans v2).
 *  - Seeing the number can carry a small fee set by the App team (paid online to the App owner, unlocks that property
 *    for a while). "Request a call back" is always free: the buyer leaves name, mobile, preferred mode and time,
 *    and it lands in the owner's Leads.
 *  - The number is masked (98XXX XX345). Call, WhatsApp and "Show number" ask once for name + mobile + consent;
 *    after that the visitor's session remembers them, so later clicks go straight through.
 *  - Every click and every email enquiry becomes a lead in the owner's Leads (duplicates merge by mobile / email).
 *  - Reveals are rate-limited per visitor (throttle:reveal).
 */
class ContactController extends Controller
{
    public const MODES = ['call' => 'call_click', 'whatsapp' => 'whatsapp_click', 'show' => 'show_number'];

    /** Call-back request: how and when the buyer wants to be contacted. */
    public const CALLBACK_MODES = ['call' => 'Phone call', 'whatsapp' => 'WhatsApp', 'email' => 'Email'];

    public const CALLBACK_SLOTS = ['any' => 'Any time', 'morning' => 'Morning (9 am–12 pm)', 'afternoon' => 'Afternoon (12–4 pm)', 'evening' => 'Evening (4–8 pm)'];

    private const SLOT_HOURS = ['any' => 10, 'morning' => 9, 'afternoon' => 12, 'evening' => 16];

    public function reveal(Request $request, string $kind, int $id)
    {
        [$owner, $c, $title, $code, $url] = $this->owner($kind, $id);
        $mode = $request->validate(['mode' => ['required', Rule::in(array_keys(self::MODES))]])['mode'];
        $modes = $c['modes'];
        abort_if($mode === 'whatsapp' && ! in_array('whatsapp', $modes, true), 422, __('WhatsApp is not enabled for this property.'));
        abort_if(in_array($mode, ['call', 'show'], true) && ! in_array('call', $modes, true), 422, __('Calls are not enabled for this property.'));

        $customer = auth('customer')->user();
        if ($fee = ContactCharges::due($customer, $kind, $owner)) {
            $msg = __('Viewing this contact costs ₹:amount. Pay once to see the number, or request a free call back.', ['amount' => \App\Support\Format::num($fee)]);
            if ($request->expectsJson()) {
                return response()->json(['message' => $msg, 'pay' => true], 402);
            }

            return back()->with('error', $msg);
        }
        $person = $request->session()->get('contact_person');
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
            ], ['mobile.regex' => __('Enter a 10-digit mobile number.'), 'consent.accepted' => __('Please agree to share your details with the owner.')]);
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

        return back()->with('revealed', [$kind.':'.$id => $out])->with('ok', __('Contact :name on :number.', ['name' => $c['name'], 'number' => $out['display']]));
    }

    /** Signed-in buyer pays the contact view fee: create the charge and send them to the hosted payment page. */
    public function unlock(Request $request, string $kind, int $id)
    {
        [$owner, , $title, $code, $url] = $this->owner($kind, $id);
        $customer = auth('customer')->user();
        if (! ContactCharges::due($customer, $kind, $owner)) {
            return redirect()->to($url.'#contact');
        }
        $unlock = ContactCharges::start($customer, $kind, $owner);
        try {
            $link = PaymentLinks::create($unlock->number, (float) $unlock->total, 'Contact view: '.$title.' ('.$code.')',
                ['name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->mobile], route('market.contact.unlock.verify', $unlock));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->to($url.'#contact')->with('error', __('Online payment is not available right now. Please request a call back instead.'));
        }
        $unlock->update(['gateway_order_id' => $link['id'], 'method' => $link['gateway']]);

        return redirect()->away($link['url']);
    }

    /** After sign-in from the contact block: back to the property's contact section. */
    public function unlockStart(string $kind, int $id)
    {
        [, , , , $url] = $this->owner($kind, $id);

        return redirect()->to($url.'#contact');
    }

    /** Return from the payment page (Razorpay signs the query string; webhooks confirm the rest). */
    public function unlockVerify(Request $request, ContactUnlock $unlock)
    {
        abort_unless($unlock->customer_id === auth('customer')->id(), 404);
        $owner = $unlock->listing_id ? Listing::withoutGlobalScopes()->find($unlock->listing_id) : Project::withoutGlobalScopes()->find($unlock->project_id);
        $back = $owner ? $owner->publicUrl().'#contact' : route('account.enquiries');
        if ($unlock->status !== 'paid' && PaymentLinks::paidCallback($request->query(), $unlock->gateway_order_id)) {
            ContactCharges::markPaid($unlock, 'razorpay', $request->query('razorpay_payment_id'));
        }
        if ($unlock->fresh()->status === 'paid') {
            return redirect()->to($back)->with('ok', __('Payment received. Tap Call, WhatsApp or Show number to contact the owner.'));
        }

        return redirect()->to($back)->with('warn', __('We have not received the payment confirmation yet. The contact unlocks automatically once the payment gateway confirms it.'));
    }

    /** Free: the buyer asks the owner to contact them (mode + preferred time). Becomes a lead in the owner's workspace. */
    public function callback(Request $request, string $kind, int $id)
    {
        [$owner, $c, $title, $code] = $this->owner($kind, $id);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'email' => 'nullable|required_if:preferred_mode,email|email|max:255',
            'preferred_mode' => ['required', Rule::in(array_keys(self::CALLBACK_MODES))],
            'preferred_slot' => ['required', Rule::in(array_keys(self::CALLBACK_SLOTS))],
            'preferred_date' => 'nullable|date|after_or_equal:today|before:+3 months',
            'message' => 'nullable|string|max:2000',
            'consent' => 'accepted',
            'website' => 'nullable|max:0',
        ], [
            'mobile.regex' => __('Enter a 10-digit mobile number.'), 'consent.accepted' => __('Please agree to share your details with the owner.'),
            'email.required_if' => __('Enter your email to be contacted by email.'),
        ]);
        $customer = auth('customer')->user();
        $mode = self::CALLBACK_MODES[$data['preferred_mode']];
        $slot = self::CALLBACK_SLOTS[$data['preferred_slot']];
        $date = isset($data['preferred_date']) ? \Carbon\Carbon::parse($data['preferred_date']) : null;
        $when = $date ? $slot.', '.$date->format('d-m-Y') : $slot;
        $body = "Requested a call back about {$title} ({$code}) — by {$mode}, {$when}.".(! empty($data['message']) ? "\nMessage: ".$data['message'] : '');
        $lead = LeadService::capture($owner->tenant_id, 'callback', [
            'name' => $data['name'], 'mobile' => $data['mobile'], 'email' => $data['email'] ?? $customer?->email, 'customer_id' => $customer?->id,
            'message' => $data['message'] ?? null, 'consent' => true,
        ], LeadService::interestFromKey($kind, $owner->id), $body);
        $followUp = ($date ?? (now()->hour >= 17 ? today()->addDay() : today()))->copy()->setTime(self::SLOT_HOURS[$data['preferred_slot']], 0);
        if ($followUp->isPast()) {
            $followUp = now()->addHour()->startOfHour();
        }
        app(\App\Support\Tenancy::class)->run(\App\Models\Tenant::find($owner->tenant_id), function () use ($lead, $data, $when, $followUp, $mode) {
            $lead = Lead::find($lead->id);
            $lead->forceFill(['preferred_mode' => $data['preferred_mode'], 'preferred_time' => mb_substr($when, 0, 40)])->saveQuietly();
            LeadService::scheduleFollowup($lead, $followUp, 'follow_up', 'Call back by '.$mode.' — '.$when);
        });
        if ($c['email']) {
            Notify::send('enquiry_received', [$c['email']], ['name' => $data['name'], 'email' => (string) ($data['email'] ?? ''), 'mobile' => $data['mobile'],
                'project' => $title.' ('.$code.')', 'message' => 'Call back request — by '.$mode.', '.$when.'.'.(! empty($data['message']) ? ' '.$data['message'] : ''),
                'link' => route('ws.leads.show', $lead)], $owner->tenant_id);
        }
        $request->session()->put('contact_person', ['name' => $data['name'], 'mobile' => $data['mobile']]);

        return back()->with('ok', __('Thanks! :name will contact you by :mode (:when).', ['name' => $c['name'], 'mode' => __($mode), 'when' => $date ? __($slot).', '.$date->format('d-m-Y') : __($slot)]));
    }

    /** Email enquiry form on a listing / layout. */
    public function email(Request $request, string $kind, int $id)
    {
        [$owner, $c, $title, $code, $url] = $this->owner($kind, $id);
        abort_unless(in_array('email', $c['modes'], true), 422, __('Email enquiries are not enabled for this property.'));
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'message' => 'required|string|max:2000',
            'consent' => 'accepted',
            'website' => 'nullable|max:0',
        ], ['mobile.regex' => __('Enter a 10-digit mobile number.'), 'consent.accepted' => __('Please agree to share your details with the owner.')]);
        $customer = auth('customer')->user();
        $lead = LeadService::capture($owner->tenant_id, $request->input('src') === 'ai' ? 'ai_search' : 'enquiry', [
            'name' => $data['name'], 'email' => $data['email'], 'mobile' => $data['mobile'], 'customer_id' => $customer?->id, 'message' => $data['message'], 'consent' => true,
        ], LeadService::interestFromKey($kind, $owner->id), $data['message']);
        if ($c['email']) {
            Notify::send('enquiry_received', [$c['email']], ['name' => $data['name'], 'email' => $data['email'], 'mobile' => $data['mobile'],
                'project' => $title.' ('.$code.')', 'message' => $data['message'], 'link' => route('ws.leads.show', $lead)], $owner->tenant_id);
        }
        $request->session()->put('contact_person', ['name' => $data['name'], 'mobile' => $data['mobile']]);

        return back()->with('ok', __('Thanks! Your message was sent to :name. They will contact you shortly.', ['name' => $c['name']]));
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
