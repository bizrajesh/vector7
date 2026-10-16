<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSearchLog extends Model
{
    public $timestamps = false;

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Customer::class); }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'relaxed' => 'array', 'used_ai' => 'boolean', 'cached' => 'boolean', 'created_at' => 'datetime'];
    }
}
