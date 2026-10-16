<?php

namespace App\Services;

use App\Listings\ListingTypes;
use App\Models\ContactUnlock;
use App\Models\Customer;
use App\Models\Listing;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Contact view charges (set by the App team, same for every tenant). Buyers browse all listings free; to see the
 * owner's / promoter's phone number they pay a small GST-inclusive fee that goes to the App owner. One payment unlocks
 * that property's contact for the validity period. A free "Request a call back" form is always available instead.
 * The charge only applies while an online payment gateway is configured.
 */
class ContactCharges
{
    public const GST_PCT = 18;

    public static function enabled(): bool
    {
        return AppSettings::get('contact.charge_enabled', '1') === '1' && PaymentLinks::enabled();
    }

    /** The configured price (GST inclusive), whether or not the gateway is on. 0 = free. */
    public static function configuredAmount(string $kind, Listing|Project|null $owner = null): float
    {
        if ($kind === 'project') {
            return max(0, (float) AppSettings::get('contact.charge_layout', '49'));
        }
        $type = $owner instanceof Listing ? $owner->type : null;
        $override = $type ? trim((string) AppSettings::get('contact.charge_type_'.$type, '')) : '';

        return max(0, (float) ($override !== '' ? $override : AppSettings::get('contact.charge_listing', '49')));
    }

    /** Price to show / charge now; null when viewing the contact is free. */
    public static function amount(string $kind, Listing|Project $owner): ?float
    {
        if (! self::enabled()) {
            return null;
        }
        $amount = self::configuredAmount($kind, $owner);

        return $amount > 0 ? $amount : null;
    }

    public static function validityDays(): int
    {
        return max(0, (int) AppSettings::get('contact.validity_days', '30'));
    }

    public static function unlocked(?Customer $customer, string $kind, int $id): ?ContactUnlock
    {
        if (! $customer) {
            return null;
        }

        return ContactUnlock::valid()->where('customer_id', $customer->id)->where($kind === 'listing' ? 'listing_id' : 'project_id', $id)->latest('id')->first();
    }

    /** Charge still to pay before this visitor may see the number (null = may see it). */
    public static function due(?Customer $customer, string $kind, Listing|Project $owner): ?float
    {
        $amount = self::amount($kind, $owner);

        return $amount && ! self::unlocked($customer, $kind, $owner->id) ? $amount : null;
    }

    public static function start(Customer $customer, string $kind, Listing|Project $owner): ContactUnlock
    {
        $total = self::amount($kind, $owner);
        abort_unless($total, 422);
        $col = $kind === 'listing' ? 'listing_id' : 'project_id';
        $open = ContactUnlock::where('customer_id', $customer->id)->where($col, $owner->id)->where('status', 'due')->where('created_at', '>', now()->subDay())->latest('id')->first();
        if ($open && (float) $open->total === $total) {
            return $open;
        }
        [$amount, $tax] = PaymentLinks::splitInclusive($total, self::GST_PCT);

        return ContactUnlock::create([
            'number' => self::nextNumber(), 'customer_id' => $customer->id, 'tenant_id' => $owner->tenant_id, $col => $owner->id,
            'title' => mb_substr($kind === 'listing' ? $owner->title : $owner->name, 0, 200),
            'amount' => $amount, 'tax' => $tax, 'total' => $total, 'status' => 'due',
        ]);
    }

    /** Idempotent. */
    public static function markPaid(ContactUnlock $unlock, string $method, ?string $paymentId = null): ContactUnlock
    {
        return DB::transaction(function () use ($unlock, $method, $paymentId) {
            $u = ContactUnlock::lockForUpdate()->find($unlock->id);
            if ($u->status !== 'paid') {
                $days = self::validityDays();
                $u->update(['status' => 'paid', 'method' => $method, 'gateway_payment_id' => $paymentId, 'paid_at' => now(), 'expires_at' => $days > 0 ? now()->addDays($days) : null]);
            }

            return $u;
        });
    }

    public static function nextNumber(): string
    {
        $prefix = 'CV-'.now()->format('Ym').'-';
        $n = ContactUnlock::where('number', 'like', $prefix.'%')->count() + 1;
        do {
            $num = $prefix.str_pad((string) $n++, 5, '0', STR_PAD_LEFT);
        } while (ContactUnlock::where('number', $num)->exists());

        return $num;
    }

    /** Listing type keys and labels for the per-type price overrides. */
    public static function typeOptions(): array
    {
        return collect(ListingTypes::all())->map(fn ($t) => $t->label())->all();
    }
}
