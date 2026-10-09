<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Ticket extends Model
{
    use BelongsToTenant, Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sla_due_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public const STATUSES = ['open' => 'Open', 'in_progress' => 'In progress', 'waiting' => 'Waiting on requester', 'resolved' => 'Resolved', 'closed' => 'Closed'];
    public const PRIORITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'];
    public const SLA_HOURS = ['low' => 72, 'medium' => 48, 'high' => 24, 'urgent' => 8];
    public const CATEGORIES = ['general' => 'General', 'billing' => 'Billing & subscription', 'technical' => 'Technical issue', 'booking' => 'Booking / payment', 'service' => 'Service request', 'account' => 'Account & login'];
    public function messages(): HasMany { return $this->hasMany(TicketMessage::class)->orderBy('id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assignee_id'); }
    public function isOverdue(): bool { return $this->sla_due_at && ! in_array($this->status, ['resolved', 'closed']) && $this->sla_due_at->isPast(); }
}
