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

class Enquiry extends Model
{
    use BelongsToTenant, Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['follow_up_on' => 'date'];
    }

    public const STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'converted' => 'Converted', 'closed' => 'Closed'];
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function plot(): BelongsTo { return $this->belongsTo(Plot::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function tenantRel(): BelongsTo { return $this->belongsTo(Tenant::class, 'tenant_id'); }
}
