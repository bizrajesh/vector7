<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Registration extends TenantModel
{
    protected $fillable = ['document_writer_id', 'document_writer_name', 'sub_registrar_office_id', 'planned_date'];

    protected function casts(): array
    {
        return ['planned_date' => 'date', 'registration_date' => 'date', 'completed_at' => 'datetime'];
    }

    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(DocumentWriter::class, 'document_writer_id')->withTrashed();
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(SubRegistrarOffice::class, 'sub_registrar_office_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(RegistrationChecklistItem::class)->orderBy('sort_order');
    }
}
