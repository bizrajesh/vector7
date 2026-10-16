<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadInterest extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    public function listing(): BelongsTo { return $this->belongsTo(Listing::class)->withTrashed(); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class)->withTrashed(); }
    public function plot(): BelongsTo { return $this->belongsTo(Plot::class); }

    public function label(): string
    {
        if ($this->listing) {
            return $this->listing->title.' ('.$this->listing->listing_code.')';
        }
        if ($this->plot && $this->project) {
            return $this->project->name.' – Plot '.$this->plot->plot_no;
        }

        return $this->project?->name ?? '—';
    }
}
