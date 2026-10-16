<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Promoter CRM lead (spec 17.6). Old tenant enquiries were migrated in as leads with source "Enquiry form". */
class Lead extends Model
{
    use Auditable, BelongsToTenant, SoftDeletes;

    public const SOURCES = [
        'enquiry' => 'Enquiry form', 'call_click' => 'Call click', 'whatsapp_click' => 'WhatsApp click', 'show_number' => 'Show number',
        'chatbot' => 'Chatbot', 'ai_search' => 'AI search match', 'booking' => 'Booking', 'walk_in' => 'Walk-in', 'phone_call' => 'Phone call',
        'referral' => 'Referral', 'social' => 'Social media', 'manual' => 'Manual',
    ];

    /** Sources a user can pick when adding a lead by hand. */
    public const MANUAL_SOURCES = ['walk_in', 'phone_call', 'referral', 'social', 'manual'];

    public const PRIORITIES = ['hot' => 'Hot', 'warm' => 'Warm', 'cold' => 'Cold'];

    public const LOST_REASONS = ['price' => 'Price', 'location' => 'Location', 'bought_elsewhere' => 'Bought elsewhere', 'not_reachable' => 'Not reachable', 'not_genuine' => 'Not genuine', 'other' => 'Other'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tags' => 'array', 'consent' => 'boolean', 'next_follow_up_at' => 'datetime', 'won_at' => 'datetime', 'lost_at' => 'datetime',
            'first_contacted_at' => 'datetime', 'last_activity_at' => 'datetime',
        ];
    }

    public function stage(): BelongsTo { return $this->belongsTo(LeadStage::class, 'lead_stage_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function interests(): HasMany { return $this->hasMany(LeadInterest::class)->latest('id'); }
    public function activities(): HasMany { return $this->hasMany(LeadActivity::class)->latest('created_at')->latest('id'); }
    public function followups(): HasMany { return $this->hasMany(LeadFollowup::class)->orderBy('due_at'); }

    /** Sales users see leads assigned to them and unassigned ones; Admin, Manager and Support see all. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->role?->base_role === 'tenant_sales') {
            $q->where(fn ($w) => $w->where('leads.assigned_to', $user->id)->orWhereNull('leads.assigned_to'));
        }

        return $q;
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? ucfirst(str_replace('_', ' ', $this->source));
    }

    public function isOverdue(): bool
    {
        return $this->next_follow_up_at && $this->next_follow_up_at->isPast() && ! $this->stage?->isClosed();
    }
}
