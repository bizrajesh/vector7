<?php

namespace App\Models;


class LayoutDocument extends TenantModel
{

    protected $fillable = ['doc_type', 'doc_no', 'doc_date', 'status'];

    protected function casts(): array
    {
        return ['doc_date' => 'date'];
    }
}
