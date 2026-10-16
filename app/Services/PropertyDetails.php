<?php

namespace App\Services;

use App\Models\FeatureMaster;
use App\Models\Listing;
use App\Models\ListingContact;
use App\Models\ListingFeature;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Features / amenities (spec 17.4) and the contact block (spec 17.5) shared by listings and layouts.
 */
class PropertyDetails
{
    public const MAX_CUSTOM = 10;

    public static function featureRules(): array
    {
        return [
            'features' => 'nullable|array|max:60',
            'features.*' => 'integer',
            'highlights' => 'nullable|array|max:'.self::MAX_CUSTOM,
            'highlights.*' => 'nullable|string|max:80',
        ];
    }

    public static function contactRules(): array
    {
        return [
            'contact_name' => 'required|string|max:100',
            'contact_mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'contact_whatsapp' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'contact_email' => 'nullable|email|max:255',
            'allow_call' => 'boolean',
            'allow_whatsapp' => 'boolean',
            'allow_email' => 'boolean',
        ];
    }

    public static function contactMessages(): array
    {
        return ['contact_mobile.regex' => 'Enter a 10-digit mobile number.', 'contact_whatsapp.regex' => 'Enter a 10-digit WhatsApp number.'];
    }

    /** Ticked features (from the masters for this type) + up to 10 custom highlights. */
    public static function syncFeatures(Listing|Project $owner, array $featureIds, array $highlights, string $type): void
    {
        $allowed = FeatureMaster::forType($type, $owner->tenant_id)->pluck('id')->all();
        $ids = array_values(array_intersect(array_map('intval', $featureIds), $allowed));
        $custom = collect($highlights)->map(fn ($h) => trim((string) $h))->filter()->unique()->take(self::MAX_CUSTOM)->values();
        DB::transaction(function () use ($owner, $ids, $custom) {
            $key = $owner instanceof Listing ? 'listing_id' : 'project_id';
            ListingFeature::withoutGlobalScopes()->where($key, $owner->id)->delete();
            foreach ($ids as $id) {
                ListingFeature::create(['tenant_id' => $owner->tenant_id, $key => $owner->id, 'feature_master_id' => $id]);
            }
            foreach ($custom as $text) {
                ListingFeature::create(['tenant_id' => $owner->tenant_id, $key => $owner->id, 'custom_text' => $text]);
            }
        });
        if ($owner instanceof Listing) {
            $owner->unsetRelation('featureRows');
            $owner->forceFill(['features_text' => implode(', ', $owner->load('featureRows')->featureNames())])->saveQuietly();
        }
    }

    public static function saveContact(Listing|Project $owner, array $d): ListingContact
    {
        $type = $owner instanceof Listing ? 'listing' : 'project';

        return ListingContact::withoutGlobalScopes()->updateOrCreate(['owner_type' => $type, 'owner_id' => $owner->id], [
            'tenant_id' => $owner->tenant_id,
            'name' => $d['contact_name'],
            'mobile' => $d['contact_mobile'],
            'whatsapp' => ($d['contact_whatsapp'] ?? null) ?: $d['contact_mobile'],
            'email' => $d['contact_email'] ?? null,
            'allow_call' => (bool) ($d['allow_call'] ?? false),
            'allow_whatsapp' => (bool) ($d['allow_whatsapp'] ?? false),
            'allow_email' => (bool) ($d['allow_email'] ?? false),
        ]);
    }

    /** Form defaults from Tenant Settings → Default listing contact. */
    public static function contactDefaults(Tenant $tenant): array
    {
        $s = $tenant->setting();

        return [
            'contact_name' => $s->contact_name ?: $tenant->name,
            'contact_mobile' => $s->contact_mobile ?: $tenant->contact,
            'contact_whatsapp' => $s->contact_whatsapp,
            'contact_email' => $s->contact_email ?: ($tenant->support_email ?: $tenant->email),
            'allow_call' => $s->allow_call ?? true,
            'allow_whatsapp' => $s->allow_whatsapp ?? true,
            'allow_email' => $s->allow_email ?? true,
        ];
    }

    /** Pull the boolean contact modes out of a request (checkboxes). */
    public static function contactFromRequest(Request $request, array $validated): array
    {
        foreach (['allow_call', 'allow_whatsapp', 'allow_email'] as $b) {
            $validated[$b] = $request->boolean($b);
        }

        return $validated;
    }

    public static function hasMode(array $contact): bool
    {
        return ! empty($contact['allow_call']) || ! empty($contact['allow_whatsapp']) || ! empty($contact['allow_email']);
    }
}
