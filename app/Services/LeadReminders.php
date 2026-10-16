<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Format;
use App\Support\Tenancy;

/**
 * Lead reminders (spec 17.6), run by the one-minute cron:
 *  - every morning at 9:00: each user gets one email listing their follow-ups due today or overdue,
 *    and the Management group gets a daily summary;
 *  - every minute: a reminder 15 minutes before each scheduled follow-up / site visit (once).
 * In-app notifications come from WorkspaceAlerts (the bell).
 */
class LeadReminders
{
    /** @return array{users:int, summaries:int} */
    public static function morning(): array
    {
        $users = 0;
        $summaries = 0;
        foreach (Tenant::where('status', 'active')->get() as $tenant) {
            if (! PlanLimiter::moduleEnabled($tenant, 'leads')) {
                continue;
            }
            app(Tenancy::class)->run($tenant, function () use ($tenant, &$users, &$summaries) {
                $due = LeadFollowup::whereNull('done_at')->where('due_at', '<=', today()->endOfDay())->with('lead.assignee')->orderBy('due_at')->get()
                    ->filter(fn ($f) => $f->lead && ! $f->lead->trashed());
                foreach ($due->groupBy(fn ($f) => $f->assigned_to ?? $f->lead->assigned_to) as $userId => $items) {
                    $user = $userId ? User::where('is_active', true)->find($userId) : null;
                    if (! $user) {
                        continue;
                    }
                    $list = $items->map(fn ($f) => '• '.Format::datetime($f->due_at).' — '.(\App\Models\LeadFollowup::KINDS[$f->kind] ?? 'Follow-up').' with '.$f->lead->name.' ('.$f->lead->mobile.')'
                        .($f->due_at->isPast() && ! $f->due_at->isToday() ? ' — OVERDUE' : '').' '.route('ws.leads.show', $f->lead))->implode("\n");
                    Notify::send('lead_followup_due', [$user->email], ['user' => $user->name, 'count' => $items->count(), 'list' => $list, 'link' => route('ws.leads.index', ['view' => 'list', 'due' => 'overdue'])], $tenant->id);
                    $users++;
                }
                $since = now()->subDay();
                Notify::groups($tenant->id, ['Management'], 'lead_daily_summary', [
                    'date' => Format::date(today()),
                    'new' => Lead::where('created_at', '>=', $since)->count(),
                    'due' => $due->count(),
                    'won' => Lead::where('won_at', '>=', $since)->count(),
                    'lost' => Lead::where('lost_at', '>=', $since)->count(),
                    'unassigned' => Lead::whereNull('assigned_to')->whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false))->count(),
                    'link' => route('ws.leads.index'),
                ]);
                $summaries++;
            });
        }

        return ['users' => $users, 'summaries' => $summaries];
    }

    /** Reminders 15 minutes before a scheduled follow-up; returns how many were sent. */
    public static function soon(): int
    {
        $n = 0;
        $rows = LeadFollowup::withoutGlobalScopes()->whereNull('done_at')->whereNull('reminded_at')
            ->whereBetween('due_at', [now(), now()->addMinutes(15)])->get();
        foreach ($rows as $f) {
            $tenant = Tenant::find($f->tenant_id);
            app(Tenancy::class)->run($tenant, function () use ($f, &$n) {
                $lead = Lead::find($f->lead_id);
                $user = User::where('is_active', true)->find($f->assigned_to ?? $lead?->assigned_to);
                if ($lead && $user) {
                    Notify::send('lead_followup_soon', [$user->email], [
                        'user' => $user->name, 'kind' => \App\Models\LeadFollowup::KINDS[$f->kind] ?? 'Follow-up', 'name' => $lead->name, 'mobile' => (string) $lead->mobile,
                        'time' => $f->due_at->format('h:i A'), 'notes' => (string) $f->notes, 'link' => route('ws.leads.show', $lead),
                    ], $f->tenant_id);
                    $n++;
                }
                $f->forceFill(['reminded_at' => now()])->saveQuietly();
            });
        }

        return $n;
    }
}
