<?php

namespace App\Services;

use App\Exceptions\PlanLimitException;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\TenantTopup;
use App\Models\TopupPack;
use App\Models\User;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Top-ups: extra users, projects, plots, storage or active listings on top of the plan.
 *  - Bought by the tenant: the pack price is per month; the top-up runs until the current plan period ends,
 *    so the tenant pays for the months left in that period (rounded up). Billed on a subscription invoice (kind "topup").
 *  - Granted by the App team: free, active at once, until a date the App team picks.
 */
class TopupService
{
    /** @return array{months:int, ends_on:Carbon, amount:float} */
    public static function quote(Tenant $tenant, TopupPack $pack, int $quantity): array
    {
        $sub = $tenant->subscription;
        if (! $sub || ! $sub->isUsable() || $sub->ends_on->lt(today())) {
            throw new PlanLimitException('Renew your plan first — top-ups run alongside an active plan.');
        }
        $months = self::monthsLeft($sub->ends_on);

        return ['months' => $months, 'ends_on' => $sub->ends_on->copy(), 'amount' => round((float) $pack->price * $quantity * $months, 2)];
    }

    /** Whole months left until the plan period ends (rounded up, a couple of days' slack, at least 1). */
    public static function monthsLeft(Carbon $endsOn): int
    {
        return max(1, (int) ceil(today()->floatDiffInMonths($endsOn->copy()->addDay()) - 0.1));
    }

    public static function purchase(Tenant $tenant, TopupPack $pack, int $quantity, ?User $by = null): SubscriptionInvoice
    {
        abort_unless($pack->is_active, 404);
        $q = self::quote($tenant, $pack, $quantity);

        return DB::transaction(function () use ($tenant, $pack, $quantity, $q, $by) {
            $topup = TenantTopup::create([
                'tenant_id' => $tenant->id, 'topup_pack_id' => $pack->id, 'key' => $pack->key, 'quantity' => $quantity,
                'units' => $pack->units * $quantity, 'months' => $q['months'], 'source' => 'purchase', 'status' => 'pending',
                'ends_on' => $q['ends_on'], 'created_by' => $by?->id,
            ]);
            $tax = round($q['amount'] * SubscriptionService::GST_PCT / 100, 2);

            return SubscriptionInvoice::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $tenant->subscription?->id,
                'plan_id' => null,
                'kind' => 'topup',
                'tenant_topup_id' => $topup->id,
                'description' => 'Top-up: '.$pack->name.($quantity > 1 ? ' × '.$quantity : '').' ('.$q['months'].' month'.($q['months'] > 1 ? 's' : '').', until '.Format::date($q['ends_on']).')',
                'number' => SubscriptionService::nextInvoiceNumber(),
                'amount' => $q['amount'],
                'tax' => $tax,
                'total' => $q['amount'] + $tax,
                'status' => 'due',
                'period_start' => today(),
                'period_end' => $q['ends_on'],
            ]);
        });
    }

    /** Free extra allowance from the App team (goodwill, trials, enterprise deals). */
    public static function grant(Tenant $tenant, string $key, int $units, ?Carbon $endsOn, ?string $note, ?User $by): TenantTopup
    {
        abort_unless(isset(TopupPack::KEYS[$key]), 422);
        $topup = TenantTopup::create([
            'tenant_id' => $tenant->id, 'key' => $key, 'quantity' => 1, 'units' => $units, 'months' => 0, 'source' => 'grant', 'status' => 'active',
            'starts_on' => today(), 'ends_on' => $endsOn, 'note' => $note, 'created_by' => $by?->id,
        ]);
        AuditLogger::log('topup_granted', $topup, null, ['key' => $key, 'units' => $units, 'ends_on' => $endsOn?->toDateString()], $tenant->id);

        return $topup;
    }

    public static function cancel(TenantTopup $topup): void
    {
        $topup->update(['status' => 'cancelled']);
        SubscriptionInvoice::withoutGlobalScopes()->where('tenant_topup_id', $topup->id)->where('status', 'due')->update(['status' => 'cancelled']);
    }
}
