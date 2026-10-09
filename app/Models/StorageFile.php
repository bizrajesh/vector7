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

class StorageFile extends Model
{
    protected $guarded = ['id'];

    public function attachable(): MorphTo { return $this->morphTo(); }
    public function sizeLabel(): string { return \App\Support\Format::bytes($this->size); }
    public function isImage(): bool { return str_starts_with($this->mime, 'image/'); }
}
