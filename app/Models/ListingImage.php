<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingImage extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_cover' => 'boolean'];
    }

    public function file(): BelongsTo { return $this->belongsTo(StorageFile::class, 'file_id'); }
    public function thumb(): BelongsTo { return $this->belongsTo(StorageFile::class, 'thumb_file_id'); }
    public function listing(): BelongsTo { return $this->belongsTo(Listing::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }

    public function url(bool $thumb = false): string
    {
        return route('market.image', ['image' => $this->id, 'size' => $thumb && $this->thumb_file_id ? 'thumb' : 'full']).'?v='.($thumb ? $this->thumb_file_id : $this->file_id);
    }
}
