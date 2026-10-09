<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Enquiry;
use App\Models\Instalment;
use App\Models\Refund;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Format;

/**
 * In-app notifications for the bell and the banner: plan usage at 80 % / 100 %,
 * subscription ending in 7 / 1 days, bookings expiring, overdue instalments, pending refunds.
 */
class WorkspaceAlerts
{
    private static array $cache = [];

    /** @return array<int, array{level:string, text:string, link:?string, banner:bool}> */
    public static function for(User $user): array
    {
        if (isset(self::$cache[$user->id])) {
            return self::$cache[$user->id];
        }
        $alerts = [];
        $tenant = $user->tenant;
        if ($tenant) {
            $sub = $tenant->subscription;
            $planLink = $user->hasPerm('tenant_settings.view') ? route('ws.settings.plan') : null;
            if ($sub) {
                $days = $sub->daysLeft();
                if (! $sub->isUsable()) {
                    $alerts[] = ['level' => 'red', 'text' => 'Your subscription has '.($sub->status === 'cancelled' ? 'been cancelled' : 'expired').'. Renew to keep using vector7.', 'link' => $planLink, 'banner' => true];
                } elseif ($days <= 7) {
                    $alerts[] = ['level' => $days <= 1 ? 'red' : 'amber', 'text' => ($sub->status === 'trial' ? 'Your trial' : 'Your subscription').' ends '.($days === 0 ? 'today' : "in $days day".($days > 1 ? 's' : '')).' ('.Format::date($sub->ends_on).').', 'link' => $planLink, 'banner' => true];
                }
            }
            foreach (PlanLimiter::usage($tenant) as $row) {
                if (! $row['unlimited'] && $row['limit'] > 0 && $row['pct'] >= 80) {
                    $alerts[] = ['level' => $row['pct'] >= 100 ? 'red' : 'amber', 'text' => "{$row['label']}: {$row['pct']}% of your plan used ({$row['display']['used']} of {$row['display']['limit']}).", 'link' => $planLink, 'banner' => true];
                }
            }
            if ($user->hasPerm('bookings.view')) {
                $n = Booking::where('status', 'active')->whereDate('valid_till', '<=', today()->addDays(1))->count();
                if ($n) {
                    $alerts[] = ['level' => 'amber', 'text' => "$n booking(s) expire within a day.", 'link' => route('ws.bookings.index', ['status' => 'active']), 'banner' => false];
                }
            }
            if ($user->hasPerm('sales.view')) {
                $n = Instalment::where('status', '!=', 'paid')->whereDate('due_date', '<', today())->whereHas('sale', fn ($q) => $q->where('status', 'sale_init'))->count();
                if ($n) {
                    $alerts[] = ['level' => 'red', 'text' => "$n instalment(s) are overdue.", 'link' => route('ws.accounts.receivables'), 'banner' => false];
                }
            }
            if ($user->hasPerm('refunds.approve')) {
                $n = Refund::where('status', 'pending')->count();
                if ($n) {
                    $alerts[] = ['level' => 'amber', 'text' => "$n refund request(s) waiting for approval.", 'link' => route('ws.refunds.index'), 'banner' => false];
                }
            }
            if ($user->hasPerm('enquiries.view')) {
                $n = Enquiry::where('status', 'new')->count();
                if ($n) {
                    $alerts[] = ['level' => 'teal', 'text' => "$n new enquir".($n > 1 ? 'ies' : 'y').'.', 'link' => route('ws.enquiries.index', ['status' => 'new']), 'banner' => false];
                }
            }
        } else {
            if ($user->hasPerm('helpdesk.view')) {
                $n = Ticket::whereNotIn('status', ['resolved', 'closed'])->where('sla_due_at', '<', now())->count();
                if ($n) {
                    $alerts[] = ['level' => 'red', 'text' => "$n ticket(s) past SLA.", 'link' => route('app.tickets.index', ['sla' => 'breached']), 'banner' => false];
                }
            }
            if ($user->hasPerm('app_enquiries.view')) {
                $n = Enquiry::where('status', 'new')->where('assigned_team', 'app')->count();
                if ($n) {
                    $alerts[] = ['level' => 'teal', 'text' => "$n new enquiries for the App team.", 'link' => route('app.enquiries.index'), 'banner' => false];
                }
            }
        }

        return self::$cache[$user->id] = $alerts;
    }
}
