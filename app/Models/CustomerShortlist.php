<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerShortlist extends Model
{
    protected $guarded = ['id'];

    public function listing(): BelongsTo { return $this->belongsTo(Listing::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
}
