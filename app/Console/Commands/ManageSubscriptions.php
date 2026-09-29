<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Console\Command;

/**
 * Subscription lifecycle: Trial → Past due (7-day grace) → Suspended (read-only).
 * Payment webhooks move tenants back to Active.
 */
class ManageSubscriptions extends Command
{
    protected $signature = 'vector7:subscriptions';

    protected $description = 'Advance trial and billing states';

    public function handle(): int
    {
        Subscription::query()->with('tenant')->whereIn('status', ['trial', 'active', 'past_due'])->lazyById()->each(function (Subscription $s) {
            $now = now();
            $next = null;

            if ($s->status === SubscriptionStatus::Trial && $s->trial_ends_at?->isPast()) {
                $next = SubscriptionStatus::PastDue;
                $s->grace_ends_at = $now->copy()->addDays(7);
            } elseif ($s->status === SubscriptionStatus::Active && $s->current_period_end?->isPast()) {
                $next = SubscriptionStatus::PastDue;
                $s->grace_ends_at = $now->copy()->addDays(7);
            } elseif ($s->status === SubscriptionStatus::PastDue && $s->grace_ends_at?->isPast()) {
                $next = SubscriptionStatus::Suspended;
            }

            if ($next && $s->tenant) {
                $s->status = $next;
                $s->save();
                $s->tenant->forceFill(['status' => $next])->save();
                $this->line("Tenant {$s->tenant_id} → {$next->value}");
            }
        });

        return self::SUCCESS;
    }
}
