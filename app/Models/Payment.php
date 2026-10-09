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

class Payment extends Model
{
    use BelongsToTenant, Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['paid_on' => 'date'];
    }

    public const MODES = ['cash' => 'Cash', 'cheque' => 'Cheque', 'bank_transfer' => 'Bank transfer', 'upi' => 'UPI'];
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function plot(): BelongsTo { return $this->belongsTo(Plot::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function receipt(): HasOne { return $this->hasOne(Receipt::class); }
    public function proof(): BelongsTo { return $this->belongsTo(StorageFile::class, 'proof_file_id'); }
}
