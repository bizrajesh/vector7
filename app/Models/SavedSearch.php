<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSearch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'alerts' => 'boolean', 'last_notified_at' => 'datetime'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

    /** Short description from the filter chips, e.g. "Flats · Near Thanjavur · Under ₹50 L". */
    public function label(): string
    {
        $chips = array_column(\App\Services\PropertySearch::chips(\App\Services\PropertySearch::clean($this->filters ?? [])), 0);

        return $chips ? implode(' · ', array_slice($chips, 0, 5)) : 'All properties';
    }

    public function url(): string
    {
        return route('market.search', \App\Services\PropertySearch::toQuery(\App\Services\PropertySearch::clean($this->filters ?? [])));
    }
}
