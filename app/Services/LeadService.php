<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadFollowup;
use App\Models\LeadInterest;
use App\Models\LeadStage;
use App\Models\Listing;
use App\Models\Plot;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Format;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Leads CRM core (spec 17.6). Every Call / WhatsApp / Show number / enquiry / chatbot hand-off / booking
 * goes through capture(): duplicate check by mobile or email within the tenant → merge into the existing lead
 * and add the new interest to its timeline; otherwise create a lead (auto-assigned round-robin when enabled).
 */
class LeadService
{
    /**
     * @param  array{name?:string, mobile?:?string, email?:?string, customer_id?:?int, message?:?string, consent?:bool, budget?:?float, preferred_location?:?string}  $person
     * @param  array{listing_id?:?int, project_id?:?int, plot_id?:?int}  $interest
     */
    public static function capture(int $tenantId, string $source, array $person, array $interest = [], ?string $activityBody = null): Lead
    {
        $tenant = Tenant::findOrFail($tenantId);

        return app(Tenancy::class)->run($tenant, function () use ($tenant, $source, $person, $interest, $activityBody) {
            $mobile = self::normaliseMobile($person['mobile'] ?? null);
            $email = isset($person['email']) && $person['email'] ? strtolower(trim($person['email'])) : null;

            [$lead, $created] = DB::transaction(function () use ($tenant, $source, $person, $mobile, $email) {
                $existing = ($mobile || $email) ? Lead::where(fn ($q) => $q
                    ->when($mobile, fn ($w) => $w->orWhere('mobile', $mobile))
                    ->when($email, fn ($w) => $w->orWhere('email', $email)))
                    ->lockForUpdate()->latest('id')->first() : null;
                if ($existing) {
                    $existing->fill(array_filter([
                        'mobile' => $existing->mobile ?: $mobile,
                        'email' => $existing->email ?: $email,
                        'customer_id' => $existing->customer_id ?: ($person['customer_id'] ?? null),
                        'budget' => $existing->budget ?: ($person['budget'] ?? null),
                        'preferred_location' => $existing->preferred_location ?: ($person['preferred_location'] ?? null),
                    ], fn ($v) => $v !== null));
                    if (! empty($person['consent'])) {
                        $existing->consent = true;
                    }
                    // A closed lead that comes back is re-opened as New.
                    if ($existing->stage?->isClosed()) {
                        $existing->lead_stage_id = self::stageId('new');
                        $existing->won_at = null;
                        $existing->lost_at = null;
                        $existing->lost_reason = null;
                    }
                    $existing->last_activity_at = now();
                    $existing->save();

                    return [$existing, false];
                }
                $lead = Lead::create([
                    'tenant_id' => $tenant->id,
                    'lead_code' => IdGenerator::next($tenant->id, 'lead'),
                    'name' => mb_substr(trim((string) ($person['name'] ?? '')) ?: 'Website visitor', 0, 100),
                    'mobile' => $mobile,
                    'email' => $email,
                    'customer_id' => $person['customer_id'] ?? null,
                    'source' => $source,
                    'budget' => $person['budget'] ?? null,
                    'preferred_location' => $person['preferred_location'] ?? null,
                    'notes' => $person['notes'] ?? null,
                    'lead_stage_id' => self::stageId('new'),
                    'priority' => $person['priority'] ?? 'warm',
                    'consent' => (bool) ($person['consent'] ?? false),
                    'last_activity_at' => now(),
                    'created_by' => Auth::guard('web')->id(),
                ]);

                return [$lead, true];
            });

            $label = self::addInterest($lead, $interest);
            self::activity($lead, $created ? 'created' : 'merged', $created ? 'Lead created from '.(Lead::SOURCES[$source] ?? $source).'.' : 'Came back via '.(Lead::SOURCES[$source] ?? $source).'.', ['source' => $source]);
            self::activity($lead, self::activityTypeFor($source), $activityBody ?? ($person['message'] ?? null), array_filter(['interest' => $label]));
            if (! empty($person['customer_id'])) {
                Customer::find($person['customer_id'])?->associateWith($tenant->id, 'enquiry');
            }
            if ($created) {
                self::autoAssign($lead);
                $lead->refresh();
                $to = $lead->assignee ? [$lead->assignee->email] : [];
                if ($to) {
                    Notify::send('lead_assigned', $to, ['user' => $lead->assignee->name, 'name' => $lead->name, 'mobile' => (string) $lead->mobile, 'interest' => $label ?: '—', 'link' => route('ws.leads.show', $lead)], $tenant->id);
                } else {
                    Notify::groups($tenant->id, ['Sales Team'], 'lead_new', ['name' => $lead->name, 'mobile' => (string) $lead->mobile, 'email' => (string) $lead->email,
                        'source' => $lead->sourceLabel(), 'interest' => $label ?: '—', 'message' => (string) ($person['message'] ?? ''), 'link' => route('ws.leads.show', $lead)]);
                }
            }

            return $lead->fresh();
        });
    }

    /** Record what the lead is interested in (listing, layout or plot); returns a readable label. */
    public static function addInterest(Lead $lead, array $interest): ?string
    {
        $interest = array_filter(array_intersect_key($interest, array_flip(['listing_id', 'project_id', 'plot_id'])));
        if (! $interest) {
            return null;
        }
        if (! empty($interest['plot_id']) && empty($interest['project_id'])) {
            $interest['project_id'] = Plot::find($interest['plot_id'])?->project_id;
        }
        $exists = LeadInterest::where('lead_id', $lead->id)
            ->where('listing_id', $interest['listing_id'] ?? null)->where('project_id', $interest['project_id'] ?? null)->where('plot_id', $interest['plot_id'] ?? null)->first();
        $row = $exists ?? LeadInterest::create($interest + ['tenant_id' => $lead->tenant_id, 'lead_id' => $lead->id]);

        return $row->load(['listing', 'project', 'plot'])->label();
    }

    public static function activity(Lead $lead, string $type, ?string $body = null, array $meta = [], ?User $user = null): LeadActivity
    {
        $user ??= Auth::guard('web')->user();
        $lead->forceFill(['last_activity_at' => now()])->saveQuietly();
        if ($user && ! $lead->first_contacted_at && in_array($type, ['call', 'whatsapp', 'email'], true)) {
            $lead->forceFill(['first_contacted_at' => now()])->saveQuietly();
        }

        return LeadActivity::create([
            'tenant_id' => $lead->tenant_id, 'lead_id' => $lead->id, 'type' => $type, 'body' => $body ? mb_substr($body, 0, 5000) : null, 'meta' => $meta ?: null,
            'actor_type' => $user ? 'user' : 'system', 'user_id' => $user?->id, 'actor_name' => $user?->name, 'created_at' => now(),
        ]);
    }

    public static function moveTo(Lead $lead, LeadStage $stage, ?string $lostReason = null, ?string $lostNotes = null): void
    {
        $from = $lead->stage?->name;
        $lead->lead_stage_id = $stage->id;
        $lead->won_at = $stage->is_won ? now() : null;
        $lead->lost_at = $stage->is_lost ? now() : null;
        $lead->lost_reason = $stage->is_lost ? $lostReason : null;
        $lead->lost_notes = $stage->is_lost ? $lostNotes : null;
        if ($stage->isClosed()) {
            $lead->next_follow_up_at = null;
            LeadFollowup::where('lead_id', $lead->id)->whereNull('done_at')->update(['done_at' => now()]);
        }
        $lead->save();
        self::activity($lead, 'stage', ($from ? "$from → " : '').$stage->name.($stage->is_lost && $lostReason ? ' ('.(Lead::LOST_REASONS[$lostReason] ?? $lostReason).')' : ''), ['stage' => $stage->key]);
    }

    public static function assign(Lead $lead, ?User $user): void
    {
        $lead->update(['assigned_to' => $user?->id]);
        self::activity($lead, 'assigned', $user ? 'Assigned to '.$user->name : 'Unassigned');
        if ($user && $user->id !== Auth::guard('web')->id()) {
            Notify::send('lead_assigned', [$user->email], ['user' => $user->name, 'name' => $lead->name, 'mobile' => (string) $lead->mobile,
                'interest' => $lead->interests()->with(['listing', 'project', 'plot'])->first()?->label() ?? '—', 'link' => route('ws.leads.show', $lead)], $lead->tenant_id);
        }
    }

    /** Round-robin among active Sales users (Tenant Settings → lead auto-assignment). */
    public static function autoAssign(Lead $lead): void
    {
        $settings = Tenant::find($lead->tenant_id)->setting();
        if (! $settings->lead_auto_assign || $lead->assigned_to) {
            return;
        }
        $sales = User::where('tenant_id', $lead->tenant_id)->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('base_role', 'tenant_sales'))->orderBy('id')->pluck('id');
        if ($sales->isEmpty()) {
            return;
        }
        $last = $settings->lead_last_assigned_user_id;
        $next = $sales->first(fn ($id) => $id > $last) ?? $sales->first();
        $settings->update(['lead_last_assigned_user_id' => $next]);
        $lead->update(['assigned_to' => $next]);
        self::activity($lead, 'assigned', 'Auto-assigned to '.User::find($next)->name.' (round-robin)');
    }

    public static function scheduleFollowup(Lead $lead, \DateTimeInterface $due, string $kind = 'follow_up', ?string $notes = null, ?int $assignTo = null): LeadFollowup
    {
        $f = LeadFollowup::create([
            'tenant_id' => $lead->tenant_id, 'lead_id' => $lead->id, 'kind' => $kind, 'due_at' => $due, 'notes' => $notes,
            'assigned_to' => $assignTo ?? $lead->assigned_to, 'created_by' => Auth::guard('web')->id(),
        ]);
        self::refreshNextFollowup($lead);
        self::activity($lead, $kind === 'site_visit' ? 'site_visit' : 'followup', LeadFollowup::KINDS[$kind].' on '.Format::datetime(\Carbon\Carbon::instance($due)).($notes ? " — $notes" : ''));
        if ($kind === 'site_visit') {
            $stage = LeadStage::where('key', 'visit_scheduled')->first();
            if ($stage && ! $lead->stage?->isClosed() && ($lead->stage?->sort ?? 0) < $stage->sort) {
                self::moveTo($lead->fresh(), $stage);
            }
        }

        return $f;
    }

    public static function completeFollowup(LeadFollowup $f, ?string $outcome = null): void
    {
        $f->update(['done_at' => now()]);
        $lead = $f->lead;
        self::refreshNextFollowup($lead);
        self::activity($lead, 'followup_done', LeadFollowup::KINDS[$f->kind].' done'.($outcome ? ": $outcome" : '.'));
        if ($f->kind === 'site_visit') {
            $stage = LeadStage::where('key', 'visited')->first();
            if ($stage && ! $lead->stage?->isClosed() && ($lead->stage?->sort ?? 0) < $stage->sort) {
                self::moveTo($lead->fresh(), $stage);
            }
        }
    }

    public static function refreshNextFollowup(Lead $lead): void
    {
        $next = LeadFollowup::where('lead_id', $lead->id)->whereNull('done_at')->min('due_at');
        $lead->forceFill(['next_follow_up_at' => $next])->saveQuietly();
    }

    public static function stageId(string $key): ?int
    {
        return LeadStage::where('key', $key)->value('id') ?? LeadStage::orderBy('sort')->value('id');
    }

    public static function normaliseMobile(?string $m): ?string
    {
        $d = preg_replace('/\D/', '', (string) $m);

        return strlen($d) >= 10 ? substr($d, -10) : null;
    }

    private static function activityTypeFor(string $source): string
    {
        return match ($source) {
            'call_click', 'whatsapp_click', 'show_number', 'chatbot', 'ai_search', 'booking' => $source,
            'enquiry' => 'enquiry',
            default => 'note',
        };
    }

    /** Interest array for a marketplace item key: L:12 (listing), P:5 (project), T:88 (plot). */
    public static function interestFromKey(string $kind, int $id): array
    {
        return match ($kind) {
            'listing' => ['listing_id' => $id],
            'project' => ['project_id' => $id],
            'plot' => ['plot_id' => $id, 'project_id' => Plot::withoutGlobalScopes()->whereKey($id)->value('project_id')],
            default => [],
        };
    }

    public static function tenantOf(string $kind, int $id): ?int
    {
        return match ($kind) {
            'listing' => Listing::withoutGlobalScopes()->whereKey($id)->value('tenant_id'),
            'project' => Project::withoutGlobalScopes()->whereKey($id)->value('tenant_id'),
            'plot' => Plot::withoutGlobalScopes()->whereKey($id)->value('tenant_id'),
            default => null,
        };
    }
}
