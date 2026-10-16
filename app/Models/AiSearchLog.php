<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSearchLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'relaxed' => 'array', 'used_ai' => 'boolean', 'cached' => 'boolean', 'created_at' => 'datetime'];
    }
}
