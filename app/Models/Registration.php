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

class Registration extends Model
{
    use BelongsToTenant, Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['checklist' => 'array', 'registration_date' => 'date', 'registered_doc_date' => 'date', 'submitted_at' => 'datetime', 'completed_at' => 'datetime', 'sold_at' => 'datetime', 'disclaimer_accepted_at' => 'datetime'];
    }

    public const STATUSES = ['draft' => 'Draft', 'ror_init' => 'ROR-Init', 'ror_completed' => 'ROR-Completed', 'sold' => 'Sold'];
    public function plot(): BelongsTo { return $this->belongsTo(Plot::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function sro(): BelongsTo { return $this->belongsTo(Sro::class); }
    public function parties(): HasMany { return $this->hasMany(RegistrationParty::class); }
    public function witnesses(): HasMany { return $this->hasMany(RegistrationWitness::class); }
    public function files(): MorphMany { return $this->morphMany(StorageFile::class, 'attachable'); }
}
