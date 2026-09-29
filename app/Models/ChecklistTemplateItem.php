<?php

namespace App\Models;


class ChecklistTemplateItem extends TenantModel
{

    protected $fillable = ['label', 'is_required', 'needs_upload', 'needs_date', 'sort_order'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'needs_upload' => 'boolean', 'needs_date' => 'boolean'];
    }
}
