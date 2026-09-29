<?php

namespace App\Models;

class RegistrationChecklistItem extends TenantModel
{
    protected $fillable = ['label', 'is_required', 'needs_upload', 'needs_date', 'sort_order', 'value', 'date_value'];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean', 'needs_upload' => 'boolean', 'needs_date' => 'boolean',
            'is_done' => 'boolean', 'date_value' => 'date', 'verified_at' => 'datetime',
        ];
    }
}
